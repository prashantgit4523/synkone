<?php

namespace App\Providers;

use App\Http\OAuth\ManageEngine\ManageEngineExtendSocialite;
use App\Listeners\LoggedOut;
use App\Listeners\LoginAttempt;
use App\Listeners\LoginFailed;
use App\Listeners\UserLocked;
use App\Listeners\LoginSuccess;
use App\Listeners\UserPasswordReset;
use Illuminate\Auth\Events\Attempting;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use App\Events\Auth\Saml2\Saml2LoginEvent;
use App\Listeners\Auth\Saml2\SingleSignOn;
use App\Listeners\Auth\Saml2\Saml2LogoutEvent;
use App\Listeners\Auth\Saml2\SingleLogOut;
use SocialiteProviders\Manager\SocialiteWasCalled;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        Registered::class => [
            SendEmailVerificationNotification::class,
        ],
        Saml2LoginEvent::class => [SingleSignOn::class],
        Saml2LogoutEvent::class => [SingleLogOut::class],
        Login::class => [LoginSuccess::class],
        Attempting::class => [LoginAttempt::class],
        Failed::class => [LoginFailed::class],
        Lockout::class => [UserLocked::class],
        PasswordReset::class => [UserPasswordReset::class],
        Logout::class => [LoggedOut::class],
        //Integration Service Providers
        SocialiteWasCalled::class => [
            \SocialiteProviders\Microsoft\MicrosoftExtendSocialite::class.'@handle',
            \SocialiteProviders\Azure\AzureExtendSocialite::class.'@handle',
            \SocialiteProviders\GitHub\GitHubExtendSocialite::class.'@handle',
            \SocialiteProviders\GitLab\GitLabExtendSocialite::class.'@handle',
            \SocialiteProviders\Bitbucket\BitbucketExtendSocialite::class.'@handle',
            \SocialiteProviders\Google\GoogleExtendSocialite::class.'@handle',
            \SocialiteProviders\DigitalOcean\DigitalOceanExtendSocialite::class.'@handle',
            ManageEngineExtendSocialite::class.'@handle'
        ],
    ];

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();
    }
}
