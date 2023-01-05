<?php

use App\Models\Administration\OrganizationManagement\Department;
use Inertia\Testing\Assert;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('has mail settings value in response', function () {
    loginWithRole();

    get('global-settings')->assertInertia(fn (Assert $page) => $page
        ->component('global-settings/GlobalSettings', false)
        ->has('mailSettings', fn (Assert $page) => $page
            ->hasAll('mail_host','mail_port','mail_encryption','mail_from_address','mail_from_name')
        )
    );
});


it('cannot update organization with existing name', function () {
    loginWithRole();

    $department = Department::factory()->create();
    $department1 = Department::factory()->create([
        'organization_id' => $department->organization_id
    ]);

    post(route('global-settings.organizations.departments.update',['id' => $department->organization_id, 'department' =>$department1->id]), ['name' => $department->name])->assertSessionHasErrors(['name' => 'The name has already been taken.']);
});