<?php

use Database\Seeders\Testing\ISO27k1Seeder;
use Inertia\Testing\Assert;
use function Pest\Laravel\get;
use App\Utils\RegularFunctions;
use function Pest\Laravel\seed;
use App\Models\Compliance\Project;
use function Pest\Laravel\getJson;
use App\Models\Compliance\Standard;
use App\Models\Compliance\ProjectControl;
use Illuminate\Testing\Fluent\AssertableJson;
use function Pest\Laravel\assertDatabaseCount;
use App\Models\Compliance\ComplianceProjectControlHistoryLog;

beforeEach(function () {
    $this->followingRedirects();
});

it('cannot access global dashboard page when unauthenticated', function () {
    get(route('global.dashboard'))
        ->assertInertia(function (Assert $page) {
            return $page->component('auth/LoginPage');
        });
});

it('can access global dashboard page when authenticated', function () {
    loginWithRole();

    get(route('global.dashboard'))
        ->assertOk()
        ->assertInertia(function (Assert $page) {
            return $page->component('global-dashboard/GlobalDashboard');
        });
});

it('can view global dashboard page only by Global Admin and Compliance Administrator role', function ($role) {
    loginWithRole($role);

    if (in_array($role, ['Global Admin', 'Compliance Administrator'])) {
        get(route('global.dashboard'))
            ->assertOk()
            ->assertInertia(function (Assert $page) {
                return $page->component('global-dashboard/GlobalDashboard');
            });
    } else {
        get(route('global.dashboard'))
            ->assertForbidden();
    }
})->with('roles');

it('shows only total count of assigned controls in controls status of dashboard', function () {
    $admin = loginWithRole();
    $data_scope = getScope($admin);

    seed(ISO27k1Seeder::class);

    $standard = Standard::inRandomOrder()->first();

    $project = Project::factory()->create([
        'standard_id' => $standard->id,
        'standard' => $standard->name
    ]);

    $controls = $standard->controls()->get(['name', 'primary_id', 'sub_id', 'id_separator', 'description', 'required_evidence', 'index'])->toArray();

    /*Creating project controls*/
    $project->controls()->createMany($controls);

    /* Added in compliance history log */
    $projectControls = ProjectControl::where('project_id', $project->id)->get();
    $todayDate = RegularFunctions::getTodayDate();
    $controlsForHistory = $projectControls->map(function ($control) use($project, $todayDate) {
        return [
            'project_id' => $project->id,
            'control_id' => $control->id,
            'applicable' => $control->applicable,
            'log_date' => $todayDate,
            'status' => $control->status,
            'control_created_date' => $control->created_at,
            'control_deleted_date' => $control->deleted_at,
            'deadline' => $control->deadline,
            'frequency' => $control->frequency,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
    });
    
    ComplianceProjectControlHistoryLog::insert($controlsForHistory->toArray());

    assertDatabaseCount('compliance_projects', 1);

    $totalControls = $project->controls()->count();
    getJson(route('global.dashboard.get-data', ['data_scope' => $data_scope, 'projects' => $project->id]))->assertJson(fn(AssertableJson $json) => $json
        ->where('success', true)
        ->where('data.allControls', $totalControls)
        ->where('data.notImplementedControls', $totalControls)
    );
});
