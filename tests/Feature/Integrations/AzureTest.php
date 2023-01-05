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

    // create an iso project
    post(route('compliance-projects-store'), [
        'data_scope' => $this->scope,
        'name' => 'ISO',
        'description' => 'ISO',
        'standard_id' => Standard::firstWhere('name', 'ISO/IEC 27001-2:2013')->id
    ]);

    $this->project = Project::first();

    // connect the actual integration
    Integration::firstWhere('slug', 'azure')->update(['connected' => true]);
});

it('redirects to Azure', function () {
    $this
        ->call('GET', '/auth/azure/redirect')
        ->assertRedirectContains('login.microsoftonline.com/common/oauth2/v2.0/authorize');
});

test('getClassificationStatus', function () {
    Http::fake([
        '*' => Http::response(
            [
                'value' => [
                    [
                        'id' => 1,
                        'name' => 'test',
                        'description' => '',
                        'tooltip' => 'test',
                        'isActive' => true,
                        'parent' => null
                    ]
                ]
            ]
        )
    ]);

    artisan('technical-control:api-map');

    expect(
        $this
            ->project
            ->controls
            ->where('status', 'Implemented')
            ->count()
        // 3 + the control that is for secure data wiping
    )->toEqual(4);
});

test('getInactiveUsersStatus', function () {
    Http::fake([
        'https://graph.microsoft.com/beta/me/informationProtection/policy/labels' => Http::response([
            'value' => [
                'parent' => null
            ]
        ]),
        '*' => Http::response([])
    ]);

    artisan('technical-control:api-map');

    expect(
        $this
            ->project
            ->controls
            ->where('status', 'Implemented')
            ->count()
    )->toEqual(5);
});

test('getSecureDataWipingStatus', function () {
    artisan('technical-control:api-map');

    expect(
        $this
            ->project
            ->controls
            ->where('status', 'Implemented')
            ->count()
    )->toEqual(1);
});