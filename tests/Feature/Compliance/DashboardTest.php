<?php

use Database\Seeders\Testing\ISO27k1Seeder;
use Inertia\Testing\Assert;
use function Pest\Laravel\get;
use App\Utils\RegularFunctions;
use function Pest\Laravel\seed;
use App\Models\Compliance\Project;
use function Pest\Laravel\getJson;
use App\Models\Compliance\Standard;
use App\Models\UserManagement\Admin;
use App\Models\Compliance\ProjectControl;

beforeEach(function (){
    $this->followingRedirects();
});

it('cannot access compliance dashboard page when unauthenticated', function () {
    get(route('compliance-dashboard'))
        ->assertInertia(function (Assert $page) {
            return $page->component('auth/LoginPage');
        });
});

it('can access compliance dashboard page only by Global Admin, Compliance Administrator and Contributor role', function ($role) {
    loginWithRole($role);

    if (in_array($role, ['Global Admin', 'Compliance Administrator', 'Contributor'])) {
        get(route('compliance-dashboard'))->assertOk()
        ->assertInertia(function (Assert $page){
            return $page->component('compliance/dashboard/Dashboard');
        });
    } else {
        get(route('compliance-dashboard'))->assertForbidden();
    }
})->with('roles');

it('can view dashboard with all tasks', function () {
    $responsible = loginWithRole('Compliance Administrator');
    $user = Admin::factory()->create();

    $data_scope = getScope($responsible);

    seed([
        ISO27k1Seeder::class
    ]);

    $standard = Standard::inRandomOrder()->first();

    $project = Project::factory()->create([
        'standard_id' => $standard->id,
        'standard' => $standard->name
    ]);

    setScope($project, $data_scope);

    $controls = $standard->controls()->get(['name', 'primary_id', 'sub_id', 'id_separator', 'description', 'required_evidence', 'index'])->toArray();

    /*Creating project controls*/
    $project->controls()->createMany($controls);

    $projectControl = ProjectControl::first();

    $projectControl->update(['approver' => $user->id, 'responsible' => $responsible->id, 'deadline' => date("Y-m-d", strtotime("+1 day")), 'frequency' => 'One-Time']);

    $request = [
        'projects' => $project->id,
        'current_date_month' => RegularFunctions::getTodayDate(),
        'data_scope' => $data_scope
    ];

    getJson(route('global.dashboard.get-caledar-data', $request))
        ->assertJsonCount(1, 'calendarTasks')
        ->assertJsonPath('calendarTasks.0.title', $projectControl->title);
});
