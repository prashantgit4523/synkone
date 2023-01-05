<?php

namespace App\ScheduledTasks\AssetManagement;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use App\ScheduledTasks\TenantScheduleTrait;

class FetchAssets
{
    use TenantScheduleTrait;

    public function __invoke()
    {
        Log::info('assets-fetch-command-triggered');
        if (tenant('id')) {
            $expired=$this->checkIfSubscriptionExpired(tenant('id'));
            if($expired){
                return false;
            }
            $this->SetUpTenantMailContent(tenant('id'));
        }

        Artisan::call('assets:fetch');

    }
}