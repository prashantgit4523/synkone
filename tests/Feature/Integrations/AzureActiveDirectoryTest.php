<?php

use App\Models\Compliance\Project;
use App\Models\Compliance\Standard;
use App\Models\Integration\Integration;
use Database\Seeders\Integration\IntegrationCategorySeeder;
use Database\Seeders\Integration\IntegrationProviderSeeder;
use Database\Seeders\Integration\IntegrationSeeder;
use Database\Seeders\TechnicalAutomation\TechnicalAutomationMappingsSeeder;
use Database\Seeders\Testing\ISO27k1Seeder;
use Database\Seeders\Testing\ISRV2Seeder;
use Illuminate\Support\Facades\Http;
use function Pest\Laravel\artisan;
use function Pest\Laravel\post;
use function Pest\Laravel\seed;

beforeEach(function () {
    $this->admin = loginWithRole();
    $this->scope = getScope($this->admin);

    seed([
        ISO27k1Seeder::class,
        ISRV2Seeder::class,
        IntegrationCategorySeeder::class,
        IntegrationProviderSeeder::class,
        IntegrationSeeder::class,
        TechnicalAutomationMappingsSeeder::class,
    ]);

    Http::fake([
    '*' => [
        'value' => [
            [
                'controlName' => 'AdminMFAV2',
                'scoreInPercentage' => 100,
                'implementationStatus' => 'ok',
                'description' => 'description'
            ]
        ]
    ]
    ]);

    Integration::firstWhere('slug', 'azure-active-directory')->update(['connected' => true]);
});

it('redirects to Azure Active Directory', function () {
    $this
        ->call('GET', '/auth/azure-active-directory/redirect')
        ->assertRedirectContains('login.microsoftonline.com/common/oauth2/v2.0/authorize');
});

test('azure ad implements the correct number of controls in ISR V2', function () {
    post(route('compliance-projects-store'), [
        'data_scope' => $this->scope,
        'name' => 'ISR',
        'description' => 'ISR',
        'standard_id' => Standard::firstWhere('name', 'ISR V2')->id
    ]);

    $project = Project::first();

    artisan('technical-control:api-map');

    // TODO: check after merging the new changes for technical automation
    expect(
        $project
            ->controls
            ->where('status', 'Implemented')
            ->count()
    )->toEqual(12);
});

test('azure ad implements the correct number of controls in ISO 27K1', function () {
    post(route('compliance-projects-store'), [
        'data_scope' => $this->scope,
        'name' => 'ISO',
        'description' => 'ISO',
        'standard_id' => Standard::firstWhere('name', 'ISO/IEC 27001-2:2013')->id
    ]);

    $project = Project::first();

    artisan('technical-control:api-map');

    // TODO: check after merging the new changes for technical automation
    expect(
        $project
            ->controls
            ->where('status', 'Implemented')
            ->count()
    )->toEqual(5);
});