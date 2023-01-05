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

it('redirects to O365', function () {
    $this
        ->call('GET', '/auth/office-365/redirect')
        ->assertRedirectContains('login.microsoftonline.com/common/oauth2/v2.0/authorize');
});