<?php

use App\Models\Compliance\Project;
use App\Models\Compliance\Standard;
use App\Models\Integration\Integration;
use Database\Seeders\Integration\IntegrationCategorySeeder;
use Database\Seeders\Integration\IntegrationProviderSeeder;
use Database\Seeders\Integration\IntegrationSeeder;

use Database\Seeders\TechnicalAutomation\TechnicalAutomationMappingsSeeder;
use Database\Seeders\Testing\ISO27k1Seeder;
use Illuminate\Support\Facades\Http;
use function Pest\Laravel\artisan;
use function Pest\Laravel\post;
use function Pest\Laravel\seed;

beforeEach(function () {
    $this->admin = loginWithRole();
    $this->scope = getScope($this->admin);

    seed([
        ISO27k1Seeder::class,
        IntegrationCategorySeeder::class,
        IntegrationProviderSeeder::class,
        IntegrationSeeder::class,
        TechnicalAutomationMappingsSeeder::class,
    ]);

    // connect the actual integration
    Integration::whereIn('slug', ['github', 'gitlab-issues'])->update(['connected' => true]);
});

it('checks all the connected integrations and implements a control once all checks are successful', function(){
    post(route('compliance-projects-store'), [
        'data_scope' => $this->scope,
        'name' => 'ISO',
        'description' => 'ISO',
        'standard_id' => Standard::firstWhere('name', 'ISO/IEC 27001-2:2013')->id
    ]);

    $project = Project::first();

    Http::fake([
        'https://api.github.com/user/*' => Http::response(
            [
                "id" => '96721873',
                "name" => "E-learning",
                "full_name" => "cyberarrow-io/E-learning",
                "visibility" => "private",
                "default_branch" => "develop"
            ]
        ),
    ]);

    artisan('technical-control:api-map');

    $control = $project
        ->controls()
        ->where('primary_id', 'A')
        ->firstWhere('sub_id', '9.2.1');

    expect($control->status)->toEqual('Implemented');

    Integration::where('slug', 'azure')->update(['connected' => true]);

    artisan('technical-control:api-map');

    $control->refresh();

    expect($control->status)->toEqual('Not Implemented');

    Http::fake([
        'https://graph.microsoft.com/beta/me/informationProtection/policy/labels' => Http::response([
            'value' => [
                'parent' => null
            ]
        ]),
        '*' => Http::response([])
    ]);

    artisan('technical-control:api-map');

    $control->refresh();
    expect($control->status)->toEqual('Implemented');

});
