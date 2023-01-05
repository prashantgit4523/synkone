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

it('redirects to Github Issues', function () {
    $this
        ->call('GET', '/auth/github-issues/redirect')
        ->assertRedirectContains('github.com/login/oauth');
});