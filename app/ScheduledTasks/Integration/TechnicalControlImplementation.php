<?php

namespace App\ScheduledTasks\Integration;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;
use App\ScheduledTasks\TenantScheduleTrait;

class TechnicalControlImplementation
{
    use TenantScheduleTrait;

    public function __invoke()
    {
        Log::info('technical-update-command-triggered');

        if(tenant('id')){
            $this->SetUpTenantMailContent(tenant('id'));
        }

        Artisan::call('technical-control:api-map');

    }
}
