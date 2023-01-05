<?php

use App\Models\Administration\OrganizationManagement\Department;
use App\Models\Compliance\ProjectControl;
use App\Models\UserManagement\Admin;
use Inertia\Testing\Assert;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertSoftDeleted;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function PHPUnit\Framework\assertFalse;

$accessDeniedResponse = [
    'success' => false,
    'message' => 'Access Denied!!',
];

beforeEach(function () {
    $this->followingRedirects();
});

it('cannot view users list page without login', function () {
    get(route('admin-user-management-view'))
        ->assertInertia(function (Assert $page) {
            return $page->component('auth/LoginPage');
        });
});

it('can view users list page only by Global Admin role', function ($role) {
    loginWithRole($role);

    if ($role === 'Global Admin')
        get(route('admin-user-management-view'))->assertOk()
            ->assertInertia(function (Assert $page) {
                return $page->component('user-management/components/UserList');
            });
    else
        get(route('admin-user-management-view'))->assertForbidden();
})->with('roles');

it('can disable user only by Global Admin role', function ($role) {
    loginWithRole($role);

    $user = Admin::factory()->create([
        'status' => 'unverified'
    ]);

    if ($role === 'Global Admin') {
        post(route('admin-user-management-disable-user', $user->id))->assertOk()->assertJson([
            'success' => true
        ]);

        $disabledUser = Admin::find($user->id);

        $this->assertTrue($disabledUser->status === 'disabled', 'User is disabled');
    } else {
        post(route('admin-user-management-disable-user', $user->id))->assertForbidden();
    }
})->with('roles');

it('cannot disable own account', function () use ($accessDeniedResponse) {
    $user = loginWithRole();
    ajaxPost(route('admin-user-management-disable-user', $user->id))->assertOk()->assertJson($accessDeniedResponse);
});

it('can activate disabled user only by Global Admin role', function ($role) {
    loginWithRole($role);

    $user = Admin::factory()->create([
        'status' => 'unverified'
    ]);

    if ($role === 'Global Admin') {
        get(route('admin-user-management-activate-user', $user->id))->assertOk()->assertJson([
            'success' => true
        ]);

        $activeUser = Admin::find($user->id);

        $this->assertTrue($activeUser->status === 'active', 'User activated');
    } else {
        get(route('admin-user-management-activate-user', $user->id))->assertForbidden();
    }
})->with('roles');

it('cannot activate own account', function () use ($accessDeniedResponse) {
    $user = loginWithRole();

    ajaxGet(route('admin-user-management-activate-user', $user->id))->assertOk()->assertJson($accessDeniedResponse);
});

it('can delete unverified users only by Global Admin role', function ($role) {
    loginWithRole($role);

    $user = Admin::factory()->create([
        'status' => 'unverified'
    ]);

    assertDatabaseHas('admins', ['id' => $user->id]);

    if ($role === 'Global Admin') {
        delete(route('admin-user-management-delete', $user->id))->assertOk()->assertJson([
            'success' => true
        ]);

        assertSoftDeleted('admins', ['id' => $user->id]);
    } else {
        delete(route('admin-user-management-delete', $user->id))->assertForbidden();
    }
})->with('roles');

it('cannot delete active users', function () {
    loginWithRole();

    // update: we can delete disabled users
    $user = Admin::factory()->create([
        'status' => 'active'
    ]);

    delete(route('admin-user-management-delete', $user->id))->assertOk()->assertJson([
        'success' => false
    ]);
});

it('cannot update all updated_at column when new user is created', function () {
    $loggedInUser = loginWithRole();

    $user = Admin::factory()->create([
        'updated_at' => '2022-01-09 13:56:13'
    ]);

    assertFalse($loggedInUser->updated_at === $user->updated_at);
});

it('cannot create a user with a global admin role if not on top department', function () {
    loginWithRole();

    $department = Department::factory()->create();

    $user = Admin::factory()
        ->make([
            'department_id' => $department->id,
            'roles' => ['Global Admin']
        ])
        ->toArray();

    $this
        ->from(route('admin-user-management-view'))
        ->post(route('admin-user-management-store', $user))
        ->assertInertia(function (Assert $page) {
            return $page
                ->component('user-management/components/UserList')
                ->where('errors', [
                    'roles.0' => 'Only the users from top department can be Global Admin.',
                    'nested_roles' => [
                        0 => 'Only the users from top department can be Global Admin.'
                    ]
                ]);
        });

    assertDatabaseCount('admins', 1);
});

it('can create a user of any role in top department', function ($role) {
    loginWithRole();

    $user = Admin::factory()
        ->make([
            'roles' => [$role]
        ])
        ->toArray();

    $user['department_id'] = 0;

    $this
        ->from(route('admin-user-management-view'))
        ->post(route('admin-user-management-store', $user))
        ->assertInertia(function (Assert $page) {
            return $page
                ->component('user-management/components/UserList')
                ->where('flash.success', 'User created successfully.');
        });

    assertDatabaseCount('admins', 2);
})->with('roles');

it('cannot disable user when user has ownerships', function () use ($accessDeniedResponse) {
    loginWithRole();

    $user = Admin::factory()->create();

    ProjectControl::factory()->create(['responsible' => $user->id]);

    ajaxPost(route('admin-user-management-disable-user', $user->id))->assertOk()->assertJson($accessDeniedResponse);
});
