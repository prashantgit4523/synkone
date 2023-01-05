<?php

namespace App\Providers;

use App\CustomProviders\Mail\GoogleMailTransport;
use Illuminate\Mail\MailManager;
use Illuminate\Support\ServiceProvider;
use App\Models\GlobalSettings\SmtpProvider;

class GoogleMailServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->afterResolving(MailManager::class, function (MailManager $manager) {
            $this->extendMailManager($manager);
        });
    }
    
    public function extendMailManager(MailManager $manager)
    {
        $manager->extend('googlemail', function () {
            $provider = SmtpProvider::where('slug', 'gmail')->first();

            return new GoogleMailTransport($provider);
        });
    }
}
