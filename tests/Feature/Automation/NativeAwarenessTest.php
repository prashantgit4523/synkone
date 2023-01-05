<?php

namespace Tests\Feature\Automation;

use App\Models\DataScope\DataScope;
use App\Models\Compliance\Project;
use App\Models\Compliance\ProjectControl;
use App\Models\Compliance\Standard;
use Database\Seeders\Compliance\ComplianceNativeAwarenessSeeder;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\seed;
use App\Models\Compliance\StandardControl;
use App\Models\PolicyManagement\Campaign\Campaign;
use App\Models\PolicyManagement\Campaign\CampaignAcknowledgment;
use App\Models\PolicyManagement\Campaign\CampaignAcknowledgmentUserToken;
use App\Models\PolicyManagement\Group\Group;
use App\Models\PolicyManagement\Policy;
use Database\Seeders\Compliance\DefaultComplianceStandardsSeeder;
use Database\Seeders\Compliance\PolicySeeder;

beforeEach(function () {
    $this->followingRedirects();
    $this->admin = loginWithRole();
    $this->data_scope = getScope($this->admin);
    seed([
        DefaultComplianceStandardsSeeder::class,
        ComplianceNativeAwarenessSeeder::class,
        PolicySeeder::class
    ]);
});


it('verify that standard controls has been mapped to awareness type', function () {
    $awarenessControls = StandardControl::where('automation', 'awareness')->count();

    $this->assertTrue($awarenessControls > 1);
});

it('verify overall awareness flow from project creation to campaign deletion', function () {
    $standardId = Standard::firstWhere('name', 'UAE IA')->id;
    //create project for main department
    $complianceProjectDetails = [
        'data_scope' => $this->data_scope,
        'standard_id' => $standardId,
        'name' => 'UAE IA Project',
        'description' => 'UAE IA Project'
    ];
    $this->post(route('compliance-projects-store', $complianceProjectDetails));
    assertDatabaseCount('compliance_projects', 1);

    //create project for sub department
    $this->dept_admin = loginWithRole('Compliance Administrator');
    $this->department = assignDepartment($this->dept_admin);
    $this->dept_data_scope = getScope($this->dept_admin);

    $departmentComplianceProjectDetails = [
        'data_scope' => $this->dept_data_scope,
        'standard_id' => $standardId,
        'name' => 'Department Project',
        'description' => 'Department Project'
    ];
    $this->post(route('compliance-projects-store', $departmentComplianceProjectDetails));
    assertDatabaseCount('compliance_projects', 2);

    //selecting project to run awareness campaign
    $project = Project::withoutGlobalScope(DataScope::class)->first();

    $complianceProjectControl = ProjectControl::withoutGlobalScope(DataScope::class)->where('project_id', $project->id)
                                                ->where('automation', 'awareness')->count();

    $this->assertTrue($complianceProjectControl > 0);

    $this->admin = loginWithRole();
    $this->data_scope = getScope($this->admin);

    //create a group
    $groups = [
        'name' => 'Group Name',
        'users' => [
            'groupsData' => [
                0 => [
                    'user_first_name' => 'Prashant',
                    'user_last_name' => 'Silpakar',
                    'user_email' => 'silpakaprashant@gmail.com',
                    'user_department' => 'dept',
                ]
            ]
        ],
        'data_scope' => $this->data_scope,
    ];

    $this->post(route('policy-management.users-and-groups.groups.store', $groups));
    assertDatabaseCount('policy_groups', 1);

    $group = Group::first();
    $policy = Policy::withoutGlobalScope(DataScope::class)->where('type', 'awareness')->first();
    $control = ProjectControl::where('automation', 'awareness')->first();

    //run campaign
    $campaignFormData = [
        'data_scope' => $this->data_scope,
        'name' => 'Awareness Campaign',
        'policies' => [
            0 => $policy->id
        ],
        'launch_date' => now()->addDays(1)->format('Y-m-d h:i:s'),
        'due_date' => now()->addDays(14)->format('Y-m-d h:i:s'),
        'timezone' => 'Asia/Dubai',
        'groups' => [
            0 => $group->id
        ],
        'auto_enroll_users' => 'yes',
        'campaign_type' => 'awareness',
        'control_id' => $control->id
    ];

    $this->post(route('policy-management.campaigns.store', $campaignFormData));
    assertDatabaseCount('policy_campaigns', 1);

    //check if campaign has been created
    $awarenessCampaign = Campaign::where('campaign_type', 'awareness-campaign')->count();
    $this->assertTrue($awarenessCampaign == 1);

    //check if responsible has been assign to all the awarness control in all department
    $controls = ProjectControl::withoutGlobalScope(DataScope::class)->where('automation', 'awareness')
                ->where('responsible', null)->count();
    $this->assertTrue($controls == 0);

    //process after email acknowledgement
    $agreedPolicy = CampaignAcknowledgment::withoutGlobalScope(DataScope::class)->first()->token;
    $campaignAcknowledgmentUserToken = CampaignAcknowledgmentUserToken::withoutGlobalScope(DataScope::class)
                                        ->first()->token;

    $formData = [
        'agreed_policy' => [
            0 => $agreedPolicy
        ],
        'campaign_acknowledgment_user_token' => $campaignAcknowledgmentUserToken
    ];
    
    $this->post(route('policy-management.campaigns.acknowledgement.confirm', $formData));

    $controls = ProjectControl::withoutGlobalScope(DataScope::class)->where('automation', 'awareness')
                            ->where('status', 'Implemented')
                            ->count();
    //In UAE IA standard there are 7 awareness control in one project so "> 7"
    //is check if it has been implemented in more than one project
    $this->assertTrue($controls > 7);

    //create new project to check if arareness control are automatically implemented or not
    $complianceProjectDetails = [
        'data_scope' => $this->data_scope,
        'standard_id' => $standardId,
        'name' => 'New UAE IA Project',
        'description' => 'new UAE IA Project'
    ];
    $this->post(route('compliance-projects-store', $complianceProjectDetails));
    assertDatabaseCount('compliance_projects', 3);

    $lastCreatedProject = Project::get()->last();
    $controls = ProjectControl::withoutGlobalScope(DataScope::class)->where('project_id', $lastCreatedProject->id)
                    ->where('automation', 'awareness')->count();

    $this->assertTrue($controls > 0);

    //check if controls changes to not implement after campaign delete
    $awarenessCampaign = Campaign::withoutGlobalScope(DataScope::class)
                        ->where('campaign_type', 'awareness-campaign')->first()->id;

    $this->from(route('policy-management.campaigns'))
            ->delete(route('policy-management.campaigns.delete', [
                'id' => $awarenessCampaign, 'data_scope' => $this->data_scope
            ]));

    $controls = ProjectControl::withoutGlobalScope(DataScope::class)->where('automation', 'awareness')
                            ->where('status', 'Implemented')
                            ->count();

    $this->assertTrue($controls == 0);
});