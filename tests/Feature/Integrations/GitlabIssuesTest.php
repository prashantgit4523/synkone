<?php

use Database\Seeders\Integration\IntegrationCategorySeeder;
use Database\Seeders\Integration\IntegrationProviderSeeder;
use Database\Seeders\Integration\IntegrationSeeder;
use function Pest\Laravel\seed;

beforeEach(function () {
    $this->admin = loginWithRole();
    seed([
        IntegrationCategorySeeder::class,
        IntegrationProviderSeeder::class,
        IntegrationSeeder::class
    ]);
});

it('redirects to Gitlab Issues', function () {
    $this
        ->call('GET', '/auth/gitlab-issues/redirect')
        ->assertRedirectContains('gitlab.com/oauth/authorize');
});