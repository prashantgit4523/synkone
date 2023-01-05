<?php

use App\Models\RiskManagement\RiskCategory;
use App\Models\RiskManagement\Project;
use App\Models\RiskManagement\RiskRegister;
use App\Models\RiskManagement\RiskStandard;
use App\Models\RiskManagement\RisksTemplate;
use Database\Seeders\Compliance\Category\StandardCategorySeeder;
use Database\Seeders\RiskManagement\RiskCategoriesSeeder;
use Database\Seeders\RiskManagement\RiskMatrix\ImpactDefaultSeeder;
use Database\Seeders\RiskManagement\RiskMatrix\LikelihoodDefaultSeeder;
use Database\Seeders\RiskManagement\RiskMatrix\RiskAcceptableScoreSeeder;
use Database\Seeders\RiskManagement\RiskMatrix\ScoreDefaultSeeder;
use Database\Seeders\RiskManagement\RiskStandardsSeeder;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\Assert;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\get;
use function Pest\Laravel\seed;

beforeEach(function () {
    $this->user = loginWithRole('Risk Administrator');
    $this->scope = getScope($this->user);
    $this->followingRedirects();

    $this->project = Project::factory()->create();
    setScope($this->project, $this->scope);

    if ($this->getName() != 'has default asset function value selected') {
        $this->risk = RiskRegister::factory()->create(['project_id' => $this->project->id]);
        setScope($this->risk, $this->scope);
    }

    seed([
        StandardCategorySeeder::class,
        RiskCategoriesSeeder::class,
        RiskStandardsSeeder::class,
        ScoreDefaultSeeder::class,
        LikelihoodDefaultSeeder::class,
        ImpactDefaultSeeder::class,
        RiskAcceptableScoreSeeder::class
    ]);
});

test('can create a risk project', function () {
    $project = Project::factory()->raw();

    $this
        ->from(route('risks.projects.index'))
        ->post(route('risks.projects.projects-store', ['data_scope' => $this->scope]), $project)
        ->assertInertia(function (Assert $page) {
            return $page->component('risk-management/project/project-details/ProjectDetails');
        });

    $this->assertDatabaseHas('risk_projects', $project);
});
//
test('assign a risk to a risk project', function () {
    $this->assertDatabaseCount('risks_register', 1);

    $data = [
        'affected_functions_or_assets' => 'test',
        'affected_properties' => ['Availability'],
        'category' => RiskCategory::first()->id,
        'data_scope' => $this->scope,
        'impact' => 2,
        'likelihood' => 1,
        'project_id' => $this->project->id,
        'risk_description' => 'My description',
        'risk_name' => 'Name',
        'treatment' => 'test',
        'is_complete' => '0',
        'treatment_options' => 'Accept'
    ];

    $this
        ->from(route('risks.projects.project-show', ['project' => $this->project->id]))
        ->post(route('risks.register.risks-store', ['data_scope' => $this->scope]), $data);

    $this->assertDatabaseCount('risks_register', 2);
});
//
it('cannot assign risk with duplicate name to a risk project', function () {
    $this->assertDatabaseCount('risks_register', 1);

    $data = [
        'affected_functions_or_assets' => 'test',
        'affected_properties' => ['Availability'],
        'category' => RiskCategory::first()->id,
        'data_scope' => $this->scope,
        'impact' => 2,
        'likelihood' => 1,
        'project_id' => $this->project->id,
        'risk_description' => 'My description',
        'risk_name' => $this->risk->name,
        'treatment' => 'test',
        'is_complete' => '0',
        'treatment_options' => 'Accept'
    ];

    $this
        ->from(route('risks.projects.project-show', ['project' => $this->project->id]))
        ->post(route('risks.register.risks-store', ['data_scope' => $this->scope]), $data);

    $this->assertDatabaseCount('risks_register', 1);
});
//
it('updates the risk', function () {
    $this->assertDatabaseCount('risks_register', 1);

    $data = [
        'affected_functions_or_assets' => 'test',
        'affected_properties' => ['Availability'],
        'category' => RiskCategory::first()->id,
        'data_scope' => $this->scope,
        'impact' => 2,
        'likelihood' => 1,
        'project_id' => $this->project->id,
        'risk_description' => 'My description',
        'risk_name' => $this->faker->title(),
        'treatment' => 'test',
        'is_complete' => '0',
        'treatment_options' => 'Accept'
    ];

    $this
        ->from(route('risks.projects.project-show', ['project' => $this->project->id]))
        ->post(route('risks.register.risks-update', ['id' => $this->risk->id, 'data_scope' => $this->scope]), $data);

    $this->assertDatabaseCount('risks_register', 1);
    $this->assertDatabaseMissing('risks_register', ['name' => $this->risk->name]);
    $this->assertDatabaseHas('risks_register', ['name' => $data['risk_name']]);
});
//
it('displays likelihood and impact correctly when csv uploaded', function () {


    $file['csv_upload'] = new UploadedFile(base_path('tests/Feature/RiskManagement/files/risk-setup-test.csv'), 'risk-setup-test.csv', 'text/csv', null, true);
    $file['project_id'] = $this->project->id;
    $this
        ->from(route('risks.projects.project-show', ['project' => $this->project->id]))
        ->post(route('risks.manual.risks-import'), $file);

    assertDatabaseCount('risks_register', 2);
    assertDatabaseHas('risks_register', ['name' => 'Risk Name']);

});

//failing!
//it('can upload csv with multiple rows', function () {
//    $file['csv_upload'] = new UploadedFile(base_path('tests/Feature/RiskManagement/files/risk-setup-multiple-rows.csv'), 'risk-setup-multiple-rows.csv', 'text/csv', null, true);
//
//    $this
//        ->from(route('risks.projects.project-show', ['project' => $this->project->id]))
//        ->post(route('risks.manual.risks-import'), $file);
//
//    assertDatabaseCount('risks_register', 2);
//    assertDatabaseHas('risks_register', ['name' => 'Risk Name']);
//});
//

it('trims the spaces for affected properties on csv manual import', function () {

    $data['csv_upload'] = new UploadedFile(base_path('tests/Feature/RiskManagement/files/risk-with-space-in-affected-properties.csv'), 'risk-with-space-in-affected-properties.csv', 'text/csv', null, true);
    $data['project_id'] = $this->project->id;
    
    $this
        ->from(route('risks.projects.project-show', ['project' => $this->project->id]))
        ->post(route('risks.manual.risks-import'), $data);

    assertDatabaseHas('risks_register', [
        'name' => 'Risk Name',
        'affected_properties' => 'Change Management'
    ]);

    assertDatabaseHas('risks_register', [
        'name' => 'Risk Name 1',
        'affected_properties' => 'Change Management'
    ]);
});

it('gets top risks in dashboard with index column', function () {
    get(route('risks.dashboard.get-dasboard-data-datatable'))
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.index', 1);

    assertDatabaseCount('risks_register', 1);
});

test('has default asset function value selected', function () {
    $count = RisksTemplate::count();
    
    $riskTemplate = RisksTemplate::factory()
        ->for(RiskCategory::factory(), 'category')
        ->for(RiskStandard::first(), 'standard')
        ->create();

    $data = [
        "selected_risk_ids" => [$riskTemplate->id],
        "data_scope" => $this->scope,
        "is_map" => 0,
        "control_mapping_project" => 0,
        "project_id" => $this->project->id
    ];

    $this->post(route('risks.wizard.yourself-risks-setup', $data))->assertOk();

    assertDatabaseCount('risks_template', $count + 1);
    assertDatabaseCount('risks_register', 1);

    assertDatabaseHas('risks_register', [
        'project_id' => $this->project->id,
        'affected_functions_or_assets' => "[{\"label\":\"All services and assets\",\"value\":\"All services and assets\"}]"
    ]);
});