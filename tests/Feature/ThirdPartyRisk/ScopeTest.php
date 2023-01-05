<?php

use App\Models\ThirdPartyRisk\Industry;
use App\Models\ThirdPartyRisk\Project;
use App\Models\ThirdPartyRisk\Project\ProjectQuestionnaire;
use App\Models\ThirdPartyRisk\Project\ProjectVendor;
use App\Models\ThirdPartyRisk\Questionnaire;
use App\Models\ThirdPartyRisk\Vendor;
use Database\Seeders\Admin\AddNewAdminRoleSeeder;
use Database\Seeders\ThirdPartyRisk\DomainsSeeder;
use Database\Seeders\ThirdPartyRisk\IndustriesSeeder;
use \App\Models\ThirdPartyRisk\Question;

beforeEach(function () {
// before each test, seed the role and industries
// and login as a Third Party Risk Admin
    $this->seed([
        AddNewAdminRoleSeeder::class,
        IndustriesSeeder::class,
        DomainsSeeder::class,
    ]);

    $this->admin = loginWithRole('Third Party Risk Administrator');
    $this->data_scope = getScope($this->admin);

    $this->project = Project::factory()
        ->in_progress()
        ->for(ProjectVendor::factory()->for(Vendor::factory(), 'vendor'), 'vendor')
        ->for(ProjectQuestionnaire::factory()->for(Questionnaire::factory()), 'questionnaire')
        ->create();

    setScope($this->project, $this->data_scope);
    setScope($this->project->vendor, $this->data_scope);
    setScope($this->project->vendor->vendor, $this->data_scope);

    // create a base scoped vendor. There will always be and extra questionnaire in a different department
    //$this->created_data = createBaseData([Vendor::class, Questionnaire::class, Project::class]);

    // use for checks, example:
    // $this->vendor_data = $created_data[Vendor::class];
    // $this->assertDatabaseHas("third_party_vendors", ['name' => $this->vendor_data["collection"]->name]);

});

it('can only see vendors created in its own department', function () {
    $admin = loginWithRole('Third Party Risk Administrator');
    $data_scope = getScope($admin);

    $vendor = Vendor::factory()->for(Industry::first())->create();
    setScope($vendor, $data_scope);

    $this->assertDatabaseHas('third_party_vendors', ['id' => $vendor->id]);
    $this->assertDatabaseCount('third_party_vendors', 2);

    $this
        ->get(route('third-party-risk.vendors.get-json-data', ['data_scope' => $data_scope]))
        ->assertJsonPath('data.data.0.id', $vendor->id)
        ->assertJsonCount(1, 'data.data');
});

it('can only see questionnaire created in  it\'s own department', function () {
    $admin = loginWithRole('Third Party Risk Administrator');
    $data_scope = getScope($admin);

    $questionnaire = Questionnaire::factory()->create();
    setScope($questionnaire, $data_scope);

    $this->assertDatabaseCount('third_party_questionnaires', 2);

    $this
        ->get(route('third-party-risk.questionnaires.get-json-data', ['data_scope' => $data_scope]))
        ->assertJsonPath('data.data.0.id', $questionnaire->id)
        ->assertJsonCount(1, 'data.data');
});

it('can only see project created in its own department', function () {
    $admin = loginWithRole('Third Party Risk Administrator');
    $data_scope = getScope($admin);

    $project = Project::factory()
        ->for(ProjectVendor::factory()->for(Vendor::factory(), 'vendor'), 'vendor')
        ->for(ProjectQuestionnaire::factory()->for(Questionnaire::factory()), 'questionnaire')
        ->create();

    setScope($project, $data_scope);

    $this->assertDatabaseHas('third_party_projects', ['id' => $project->id]);
    $this->assertDatabaseCount('third_party_projects', 2);

    $this
        ->get(route('third-party-risk.projects.get-json-data', ['data_scope' => $data_scope]))
        ->assertJsonPath('projects.0.id', $project->id)
        ->assertJsonCount(1, 'projects');
});

it('can only see the vendor maturity data for its own department', function () {
    $admin = loginWithRole('Third Party Risk Administrator');
    $data_scope = getScope($admin);

    $project = Project::factory()
        ->in_progress()
        ->for(ProjectVendor::factory()->for(Vendor::factory(), 'vendor'), 'vendor')
        ->for(ProjectQuestionnaire::factory()->for(Questionnaire::factory()), 'questionnaire')
        ->create();

    setScope($project, $data_scope);
    setScope($project->vendor, $data_scope);
    setScope($project->vendor->vendor, $data_scope);

    $this->assertDatabaseCount('third_party_vendors', 2);

    $this->get(route('third-party-risk.dashboard.get-vendors-data', ['data_scope' => $data_scope]))
        ->assertOk()
        ->assertJsonCount(3)
        ->assertJsonFragment([
            'level' => 1,
            'color' => "#ff0000",
            'name' => "Level 1",
        ])
        ->assertJsonPath('projects_progress.In Progress', 1);
});

it('scopes project options when creating a project', function () {
    $admin = loginWithRole('Third Party Risk Administrator');
    $data_scope = getScope($admin);

    $questionnaire = Questionnaire::factory()->has(Question::factory())->create();
    setScope($questionnaire, $data_scope);

    $vendor = Vendor::factory()->create();
    setScope($vendor, $data_scope);

    $this
        ->get(route('third-party-risk.projects.options', ['data_scope' => $data_scope]))
        ->assertOk()
        ->assertJsonCount(1, 'vendors')
        ->assertJsonCount(1, 'questionnaires');
});

//it('can only see the top vendors from it\'s own department ', function () {
//    $admin = loginWithRole('Third Party Risk Administrator');
//    $data_scope = getScope($admin);
//
//    for ($i = 0; $i < 4; $i++) {
//        $vendor = Vendor::factory()->create();
//        $project = Project::factory()
//            ->for(ProjectVendor::factory()->for($vendor, 'vendor'), 'vendor')
//            ->for(ProjectQuestionnaire::factory()->for(Questionnaire::factory()), 'questionnaire')
//            ->in_progress()
//            ->create();
//
//        echo $project->name . "\n";
//        setScope($project, $data_scope);
//        setScope($project->vendor, $data_scope);
//        setScope($vendor, $data_scope);
//    }
//
//    $this
//        ->get(route('third-party-risk.dashboard.get-top-vendors', ['data_scope' => $data_scope]))
//        ->assertOk()
//        ->dd()
//        ->assertJsonPath('data.total', 4);
//});
