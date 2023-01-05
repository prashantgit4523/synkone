<?php

use App\Models\Compliance\ProjectControl;
use App\Models\Compliance\Standard;
use App\Models\RiskManagement\Project as RiskProject;
use Database\Seeders\DocumentAutomation\BaseDocumentTemplatesSeeder;
use Database\Seeders\Compliance\Category\StandardCategorySeeder;
use Database\Seeders\Testing\ISO27k1Seeder;
use Database\Seeders\Testing\ISRV2Seeder;
use Database\Seeders\ThirdPartyRisk\IndustriesSeeder;

use App\Models\ThirdPartyRisk\Project as TPRProject;
use App\Models\ThirdPartyRisk\Project\ProjectQuestionnaire;
use App\Models\ThirdPartyRisk\Project\ProjectVendor;
use App\Models\ThirdPartyRisk\Questionnaire;
use App\Models\ThirdPartyRisk\Vendor;

use Illuminate\Support\Facades\Http;
use Database\Seeders\TechnicalAutomation\TechnicalAutomationMappingsSeeder;
use Database\Seeders\Integration\IntegrationCategorySeeder;
use Database\Seeders\Integration\IntegrationProviderSeeder;
use Database\Seeders\Integration\IntegrationSeeder;
use App\Models\Integration\Integration;
use App\Models\Controls\KpiControlStatus;
use Database\Seeders\Compliance\Category\UpdateCategoryToStandardSeeder;
use Database\Seeders\Compliance\DefaultComplianceStandardsSeeder;
use Database\Seeders\Compliance\SOC2TSC2017Seeder;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\seed;
use function Pest\Laravel\artisan;
use Database\Seeders\Kpi\UpdateKpiEnableIntegrationControls;

beforeEach(function () {
    ini_set('max_execution_time', '400');
    $this->user = loginWithRole('Global Admin');
    $this->scope = getScope($this->user);

    $this->followingRedirects();

    seed([
        StandardCategorySeeder::class,
        ISO27k1Seeder::class,
        ISRV2Seeder::class,
        BaseDocumentTemplatesSeeder::class
    ]);
    
});

it('Risk Management Report, verify the controls are Implemented/Not Implemented as per the requirements', function () {
    // To create compliance project under Standard: ISO/IEC 27001-2:2013
    $defaultComplianceProjectDetail = [
        'data_scope' => $this->scope,
        'standard_id' => Standard::firstWhere('name', 'ISO/IEC 27001-2:2013')->id,
        'name' => 'ISO Project',
        'description' => 'ISO Project Description'
    ];

    $this
        ->from(route('compliance-projects-create'))
        ->post(route('compliance-projects-store', $defaultComplianceProjectDetail));

    assertDatabaseCount('compliance_projects', 1);  // check if compliance project created
    
    $countImplementedCompliance = ProjectControl::where('automation', 'document')
                                                ->where('status', 'Implemented')
                                                ->count();
                                                
    $this->assertTrue($countImplementedCompliance == 14);   // for the first, compliance project
                                                            // under Standard: ISO/IEC 27001-2:2013 will
                                                            // have 14 compliance_project_controls with
                                                            // status = Implemented & automation = document

    // Create Risk Management project
    $riskData = [
        'data_scope' => $this->scope,
        'name' => 'Risk Project',
        'description' => 'Risk Project Description'
    ];

    $this->from(route('risks.projects.projects-create'))
         ->post(route('risks.projects.projects-store', $riskData));

    $countImplementedCompliance2 = ProjectControl::where('automation', 'document')
                                                ->where('status', 'Implemented')
                                                ->count();
                                                                                          
    $this->assertTrue($countImplementedCompliance2 == 18);  // after risk project has been created
                                                            // compliance project
                                                            // under Standard: ISO/IEC 27001-2:2013 will
                                                            // have 18 compliance_project_controls with
                                                            // status = Implemented & automation = document
    
    // Delete Risk Project
    $riskProject = RiskProject::select('id')->orderBy('id', 'desc')->first();
    
    $this->from(route('risks.projects.index'))
         ->delete(route('risks.projects.projects-delete', $riskProject->id));

    $countImplementedCompliance3 = ProjectControl::where('automation', 'document')
                                        ->where('status', 'Implemented')
                                        ->count();
                                        
    $this->assertTrue($countImplementedCompliance3 == 14);  // after risk project has been deleted
                                                            // compliance project will again
                                                            // have 14 compliance_project_controls with
                                                            // status = Implemented & automation = document
});

it('Third-Party Risk Assessment Report, verify controls are Implemented as per the requirements', function () {
    // To create compliance project under Standard: ISR V2
    $defaultComplianceProjectDetail = [
        'data_scope' => $this->scope,
        'standard_id' => Standard::firstWhere('name', 'ISR V2')->id,
        'name' => 'Test Project',
        'description' => 'Test Project Description'
    ];

    $this
        ->from(route('compliance-projects-create'))
        ->post(route('compliance-projects-store', $defaultComplianceProjectDetail));
    
    assertDatabaseCount('compliance_projects', 1);  // check if compliance project created

    $countImplementedCompliance = ProjectControl::where('automation', 'document')
                                                ->where('status', 'Implemented')
                                                ->count();
                                                
    
    $this->assertTrue($countImplementedCompliance == 7);   // for the first, compliance project
                                                            // under Standard: ISR V2 will
                                                            // have 7 compliance_project_controls with
                                                            // status = Implemented & automation = document

    // Create Third Party Risk project
    $this->seed([
        IndustriesSeeder::class
    ]);

    $project = TPRProject::factory()
        ->for(ProjectVendor::factory()->for(Vendor::factory(), 'vendor'), 'vendor')
        ->for(ProjectQuestionnaire::factory()->for(Questionnaire::factory()), 'questionnaire')
        ->create();
    setScope($project, $this->scope);

    $countImplementedCompliance2 = ProjectControl::where('automation', 'document')
                                                ->where('status', 'Implemented')
                                                ->count();
                                                
    $this->assertTrue($countImplementedCompliance2 == 8);  // after third party risk project has been created
                                                            // compliance project
                                                            // will have 8 compliance_project_controls with
                                                            // status = Implemented & automation = document
    // Delete Third Party Risk Project
    $this
        ->from(route('third-party-risk.projects.index', ['data_scope' => $this->scope]))
        ->delete(route('third-party-risk.projects.destroy', [$project->id, 'data_scope' => $this->scope]))
        ->assertRedirect(route('third-party-risk.projects.index', ['data_scope' => $this->scope]))
        ->assertSessionHas('success', 'Project deleted successfully.');
    $this->assertSoftDeleted('third_party_projects', ['name' => $project->name]);

    $countImplementedCompliance3 = ProjectControl::where('automation', 'document')
                                        ->where('status', 'Implemented')
                                        ->count();
                                        
    $this->assertTrue($countImplementedCompliance3 == 7);  // after third party risk project has been deleted
                                                            // compliance project will again
                                                            // have 7 compliance_project_controls with
                                                            // status = Implemented & automation = document
});

it('Performance Evaluation Report, verify data in kpi_control_status table as per integration connected', function () {
    seed([
        DefaultComplianceStandardsSeeder::class,
        SOC2TSC2017Seeder::class,
        UpdateCategoryToStandardSeeder::class,
        IntegrationCategorySeeder::class,
        IntegrationProviderSeeder::class,
        IntegrationSeeder::class,
        TechnicalAutomationMappingsSeeder::class,
        UpdateKpiEnableIntegrationControls::class
    ]);

    // To create compliance project under Standard: ISO/IEC 27001-2:2013
    $defaultComplianceProjectDetail = [
        'data_scope' => $this->scope,
        'standard_id' => Standard::firstWhere('name', 'ISO/IEC 27001-2:2013')->id,
        'name' => 'Test Project',
        'description' => 'Test Project Description'
    ];

    $this
        ->from(route('compliance-projects-create'))
        ->post(route('compliance-projects-store', $defaultComplianceProjectDetail));

    assertDatabaseCount('compliance_projects', 1);  // check if compliance project created

    // Connect 'Intune' as integration
    Integration::firstWhere('slug', 'intune')->update(['connected' => true]);
    Http::fake([
        'https://graph.microsoft.com/v1.0/*' => Http::response(
            [
                'value' => [
                    [
                        'id' => '0da30048',
                        "operatingSystem" => "Windows",
                        "operatingSystemVersion" => "10.0.19042.1237"
                    ]
                ]
            ]
        ),
        'https://graph.microsoft.com/beta/*' => Http::response(
            [
                'value' => [
                    [
                        "id" => "0f8ca9c2-c85c-4ca1-a02a-58d09f5bb25d",
                        "createdDateTime" => "2022-04-22T09:47:41.9500806Z",
                        "description" => null,
                        "lastModifiedDateTime" => "2022-05-30T09:56:51.596769Z",
                        "displayName" => "Technical Automation - Auto Updates",
                        "version" => 2,
                        "passwordRequired" => false,
                        "passwordBlockSimple" => false,
                        "passwordRequiredToUnlockFromIdle" => false,
                        "passwordMinutesOfInactivityBeforeLock" => null,
                        "passwordExpirationDays" => null,
                        "passwordMinimumLength" => null,
                        "passwordMinimumCharacterSetCount" => null,
                        "passwordRequiredType" => "deviceDefault",
                        "passwordPreviousPasswordBlockCount" => null,
                        "deviceCompliancePolicyScript" => [
                            "rulesContent" => "ewogICJSdWxlcyI6IFsKICAgIHsKICAgICAgIlNldHRpbmdOYW1lIjogIkNvbXBsaWFudEF1dG9VcGRhdGVzIiwKICAgICAgIk9wZXJhdG9yIjogIklzRXF1YWxzIiwKICAgICAgIkRhdGFUeXBlIjogIkJvb2xlYW4iLAogICAgICAiT3BlcmFuZCI6IHRydWUsCiAgICAgICJNb3JlSW5mb1VybCI6ICJodHRwczovLzRzeXNvcHMuY29tL2FyY2hpdmVzL21hbmFnaW5nLXdpbmRvd3MtdXBkYXRlcy13aXRoLW1pY3Jvc29mdC1pbnR1bmUvIiwKICAgICAgIlJlbWVkaWF0aW9uU3RyaW5ncyI6IFsKICAgICAgICB7CiAgICAgICAgICAiTGFuZ3VhZ2UiOiAiZW5fVVMiLAogICAgICAgICAgIlRpdGxlIjogIkF1dG8gVXBkYXRlcyIsCiAgICAgICAgICAiRGVzY3JpcHRpb24iOiAiQXV0byB1cGRhdGVzIHNob3VsZCBiZSBlbmFibGVkIGluIGVuZHVzZXIgZGV2aWNlcy4iCiAgICAgICAgfQogICAgICBdCiAgICB9CiAgXQp9Cg=="
                        ],
                        "assignments" => [
                            [
                                "target" => [
                                    "groupId" => "b4f65d59-9417-4d2f-aac4-d60c7a93778c"
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        )
    ]);

    artisan('technical-control:api-map');
    artisan('kpi_controls:update');
    $this->assertTrue(KpiControlStatus::count() > 0);

    // Disconnect 'Intune' as integration
    Integration::firstWhere('slug', 'intune')->update(['connected' => false]);
    artisan('technical-control:api-map');
    artisan('kpi_controls:update');
    $this->assertTrue(KpiControlStatus::count() == 0);
});