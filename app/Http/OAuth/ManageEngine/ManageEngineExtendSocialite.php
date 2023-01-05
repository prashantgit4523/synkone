<?php

namespace App\Http\OAuth\ManageEngine;

use SocialiteProviders\Manager\SocialiteWasCalled;

class ManageEngineExtendSocialite
{
    public function handle(SocialiteWasCalled $socialiteWasCalled)
    {
        $socialiteWasCalled->extendSocialite('manage-engine-cloud', Provider::class);
    }
}