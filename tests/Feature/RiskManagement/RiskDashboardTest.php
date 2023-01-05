<?php

use App\Models\RiskManagement\RiskRegister;
use App\Models\RiskManagement\Project;
use \Inertia\Testing\Assert;

it('in the dashboard, risk should appear closed when it has been closed', function () { //571
    $user = loginWithRole();
    $scope = getScope($user);

    $project = Project::factory()->create();
    setScope($project, $scope);

    $risk = RiskRegister::factory()->create(['project_id' => $project->id]);
    setScope($risk, $scope);

    $this
        ->get(route('risks.register.risks-edit', ['id' => 1, 'data_scope' => $scope]))
        ->assertInertia(function (Assert $page) {
            return $page
                ->component('risk-management/risk-register/components/RiskRegisterCreate');
        });
});