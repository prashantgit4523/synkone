<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Contracts\Factory;
use App\Http\OAuth\AzureServiceManagementProvider;
use App\Http\OAuth\ManageEngine\Provider as ManageEngineProvider;
use App\Http\OAuth\OracleCloudProvider;

class CustomIntegrationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $socialite = $this->app->make(Factory::class);

        $services = $this->getServices();
        foreach ($services as $service) {
            $socialite->extend(
                $service['name'],
                function () use ($socialite, $service) {
                    $config = config($service['config_key']);
                    return $socialite->buildProvider($service['provider'], $config);
                }
            );
        }
    }


    private function getServices(){
        return [
            [
                'name' => 'azure-service-management',
                'config_key' => 'services.azure',
                'provider' => AzureServiceManagementProvider::class
            ],
            [
                'name' => 'manage-engine-cloud',
                'config_key' => 'services.manage-engine',
                'provider' => ManageEngineProvider::class
            ],
            [
                'name' => 'oraclecloud',
                'config_key' => 'services.oraclecloud',
                'provider' => OracleCloudProvider::class
            ]
        ];
    }
}
