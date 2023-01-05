<?php

use App\Models\Compliance\Project;
use App\Models\Compliance\Standard;
use App\Models\Compliance\ProjectControl;
use App\Models\Integration\Integration;
use Database\Seeders\Integration\IntegrationCategorySeeder;
use Database\Seeders\Integration\IntegrationProviderSeeder;
use Database\Seeders\Integration\IntegrationSeeder;

use Database\Seeders\TechnicalAutomation\TechnicalAutomationMappingsSeeder;
use Database\Seeders\Testing\ISO27k1Seeder;
use Database\Seeders\Testing\UAEIASeeder;
use Illuminate\Support\Facades\Http;
use function Pest\Laravel\artisan;
use function Pest\Laravel\post;
use function Pest\Laravel\seed;

beforeEach(function () {
    $this->admin = loginWithRole();
    $this->scope = getScope($this->admin);

    seed([
        ISO27k1Seeder::class,
        UAEIASeeder::class,
        IntegrationCategorySeeder::class,
        IntegrationProviderSeeder::class,
        IntegrationSeeder::class,
        TechnicalAutomationMappingsSeeder::class,
    ]);

    // connect the actual integration
    Integration::firstWhere('slug', 'github')->update(['connected' => true]);
});

it('redirects to Github', function () {
    $this
        ->call('GET', '/auth/github/redirect')
        ->assertRedirectContains('github.com/login/oauth');
});

//it('test', function () {
//    $abstractUser = Mockery::mock('Laravel\Socialite\Two\User');
//
//    $abstractUser
//        ->shouldReceive('getId')
//        ->andReturn(rand())
//        ->shouldReceive('getName')
//        ->andReturn($this->faker->name())
//        ->shouldReceive('getEmail')
//        ->andReturn($this->faker->email())
//        ->shouldReceive('getAvatar')
//        ->andReturn('https://en.gravatar.com/userimage');
//
//    Socialite::shouldReceive('driver->user')->andReturn($abstractUser);
//    $this
//        ->get('/auth/github/callback');
//
//    \Pest\Laravel\assertDatabaseHas('integrations', ['slug' => 'github']);
//})->only();

test('github implements the correct number of controls in an ISO 27K1 project', function () {
    post(route('compliance-projects-store'), [
        'data_scope' => $this->scope,
        'name' => 'ISO',
        'description' => 'ISO',
        'standard_id' => Standard::firstWhere('name', 'ISO/IEC 27001-2:2013')->id
    ]);

    $project = Project::first();

    Http::fake([
        'https://api.github.com/user' => Http::response(
            [
                'two_factor_authentication' => true,
                'site_admin' => true,
                'id' => 123,
                'login' => 'User'
            ]
        ),
        'https://api.github.com/user/repos' => Http::response(
            [
                [
                    'name' => 'My-repo',
                    'owner' => [
                        'login' => 'User'
                    ]
                ]
            ]
        ),
        'https://api.github.com/repos/octocat/My-repo/commits' => Http::response(
            [
                [
                    'sha' => '123',
                    'commit' => [
                        'message' => 'My commit message',
                        'committer' => [
                            'date' => '18/08/2022 13:00'
                        ]
                    ]
                ]
            ]
        ),

        'https://api.github.com/repos/User/My-repo/events' => Http::response(
            [
                [
                    'created_at' => '2022-09-16 13:32:20',
                    'actor' => 'me',
                    'id' => 'DateTimeInterface',
                    'display_login' => 'display_login',
                    'login' => 'login',
                ]
            ]
        ),

        'https://api.github.com/repos/User/My-repo/pulls' => Http::response(
            [
                [
                    'id' => '10',
                    "user" => [
                        "login" => "octocat",
                        "type" => "user"
                    ],
                    "title" => "Amazing new feature",
                    "number" => 1347,
                    "created_at" => "2011-04-10T20:09:31Z",
                    "updated_at" => "2014-03-03T18:58:10Z",
                ]
            ]
        ),

        "https://api.github.com/repos/User/My-repo/branches/master/protection" => Http::response(
            [
                [
                    "protected_repository" => "Hello-World",
                    "protected_branch" => "master",
                    "production_branch_restrictions" => true
                ]
            ]
        ),
    ]);

    artisan('technical-control:api-map');

    expect(
        $project
            ->controls
            ->where('status', 'Implemented')
            ->count()
    )->toEqual(1);
});

test('github implements the correct number of controls in an UAE IA project', function () {
    post(route('compliance-projects-store'), [
        'data_scope' => $this->scope,
        'name' => 'UAE IA',
        'description' => 'UAE IA',
        'standard_id' => Standard::firstWhere('name', 'UAE IA')->id
    ]);

    $project = Project::first();

    Http::fake([
        //getAdminMfaStatus
        'https://api.github.com/user' => Http::response(
            [
                'two_factor_authentication' => true,
                'site_admin' => true,
                'id' => 123,
                'login' => 'User'
            ]
        ),
        //getGitStatus
        'https://api.github.com/user/repos' => Http::response(
            [
                [
                    'name' => 'My-repo',
                    'owner' => [
                        'login' => 'User'
                    ]
                ]

            ]
        ),
        //getGitStatus
        'https://api.github.com/repos/User/My-repo/commits' => Http::response(
            [
                [
                    'sha' => '123',
                    'commit' => [
                        'message' => 'My commit message',
                        'committer' => [
                            'name' => 'User',
                            'date' => '18/08/2022 13:00'
                        ]
                    ]
                ]
            ]
        ),

        'https://api.github.com/repos/User/My-repo/events' => Http::response(
            [
                [
                    'created_at' => '2022-09-16 13:32:20',
                    'actor' => 'me',
                    'id' => 'DateTimeInterface',
                    'display_login' => 'display_login',
                    'login' => 'login',
                ]
            ]
        ),

        'https://api.github.com/repos/User/My-repo/pulls' => Http::response(
            [
                [
                    'id' => '10',
                    "user" => [
                        "login" => "octocat",
                        "type" => "user"
                    ],
                    "title" => "Amazing new feature",
                    "number" => 1347,
                    "created_at" => "2011-04-10T20:09:31Z",
                    "updated_at" => "2014-03-03T18:58:10Z",
                ]
            ]
        ),

        "https://api.github.com/repos/User/My-repo/branches/master/protection" => Http::response(
            [
                [
                    "protected_repository" => "Hello-World",
                    "protected_branch" => "master",
                    "production_branch_restrictions" => true
                ]
            ]
        ),
    ]);


    artisan('technical-control:api-map');

    expect(
        $project
            ->controls
            ->where('status', 'Implemented')
            ->count()
    )->toEqual(1);
});
