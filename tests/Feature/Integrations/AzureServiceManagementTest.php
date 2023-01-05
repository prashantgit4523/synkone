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

it('redirects to Azure Service Management', function () {
    $this
        ->call('GET', '/auth/azure-service-management/redirect')
        ->assertRedirectContains('login.microsoftonline.com/common/oauth2/v2.0/authorize');
});