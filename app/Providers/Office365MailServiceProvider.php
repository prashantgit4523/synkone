<?php

namespace App\Providers;

use Illuminate\Mail\MailManager;
use Illuminate\Support\ServiceProvider;
use App\CustomProviders\Mail\Office365MailTransport;
use App\Models\GlobalSettings\SmtpProvider;

class Office365MailServiceProvider extends ServiceProvider
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
        $manager->extend('office365mail', function () {
            $provider = SmtpProvider::where('slug', 'office-365')->first();

            return new Office365MailTransport($provider);
        });
    }
}
