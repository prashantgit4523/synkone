<?php

use App\Models\Integration\Integration;
use Database\Seeders\Integration\IntegrationCategorySeeder;
use Database\Seeders\Integration\IntegrationProviderSeeder;
use Database\Seeders\Integration\IntegrationSeeder;
use Database\Seeders\TechnicalAutomation\TechnicalAutomationMappingsSeeder;
use Database\Seeders\Testing\ISO27k1Seeder;
use Illuminate\Support\Facades\Http;
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

    Http::fake([
        'https://sdpondemand.manageengine.com/api/v3/assets' => [
            'assets' => [
                [
                    'name' => 'test',
                    'product' => [
                        'product_type' => [
                            'name' => 'product name'
                        ]
                    ],
                    'created_by' => [
                        'name' => 'user name'
                    ],
                    'state' => [
                        'internal_name' => 'internal name'
                    ]
                ]
            ]
        ],
        'https://sdpondemand.manageengine.com/api/v3/changes' => [
            'changes' => [
                [
                    'id' => 1,
                    'title' => 'title',
                    'description' => 'description',
                    'created_time' => [
                        'display_value' => 2
                    ],
                    'change_type' => [
                        'name' => 'name'
                    ],
                    'impact' => [
                        'name' => 'name'
                    ],
                    'approval_status' => [
                        'name' => 'name'
                    ],
                    'risk' => [
                        'name' => 'name'
                    ]
                ]
            ]
        ],
        'https://sdpondemand.manageengine.com/api/v3/requests' => [
            'requests' => [
                [
                    'id' => 1,
                    'subject' => '',
                    'group' => null,
                    'status' => [
                        'in_progress' => false,
                        'internal_name' => 'Resolved'
                    ],
                    'created_time' => [
                        'display_value' => ''
                    ],
                    'requester' => [
                        'name' => 'name'
                    ],
                    'technician' => [
                        'name' => 'name'
                    ]
                ]
            ]
        ],
        'https://sdpondemand.manageengine.com/api/v3/requests/1' => [
            'request' => [
                'description' => 'description',
                'category' => [
                    'name' => 'Network'
                ],
                'request_type' => [
                    'name' => 'Incident'
                ],
                'resolution' => [
                    'content' => 'content'
                ],
                'subcategory' => null,
                'impact' => null,
                'priority' => null,
                'resolved_time' => null,
                'sla' => null
            ]
        ],
        'https://accounts.zoho.com/oauth/v2/token' => [
            'access_token' => 'token123'
        ]
    ]);

    // connect the actual integration
    Integration::firstWhere('slug', 'manage-engine-cloud')->update(['connected' => true]);
});

it('redirects to Zoho - Manage Engine Cloud', function () {
    $this
        ->call('GET', '/auth/manage-engine-cloud/redirect')
        ->assertRedirectContains('accounts.zoho.com/oauth/v2/auth');
});

//test('manage engine cloud implements the correct number of controls in PCI DSS 4.0', function () {
//    post(route('compliance-projects-store'), [
//        'data_scope' => $this->scope,
//        'name' => 'PCI DSS',
//        'description' => 'PCI DSS',
//        'standard_id' => Standard::firstWhere('name', 'PCI DSS 4.0')->id
//    ]);
//
//    $project = Project::first();
//
//    artisan('technical-control:api-map');
//
//    expect(
//        $project
//            ->controls
//            ->where('status', 'Implemented')
//            ->count()
//    )->toEqual(17);
//});

//test('manage engine cloud implements the correct number of controls in ISO 27k1', function () {
//    post(route('compliance-projects-store'), [
//        'data_scope' => $this->scope,
//        'name' => 'ISO',
//        'description' => 'ISO',
//        'standard_id' => Standard::firstWhere('name', 'ISO/IEC 27001-2:2013')->id
//    ]);
//
//    $project = Project::first();
//
//    artisan('technical-control:api-map');
//
//    expect(
//        $project
//            ->controls
//            ->where('status', 'Implemented')
//            ->count()
//    )->toEqual(9);
//});