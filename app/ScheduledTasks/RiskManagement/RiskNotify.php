<?php

namespace App\ScheduledTasks\RiskManagement;

use App\ScheduledTasks\TenantScheduleTrait;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class RiskNotify
{
    use TenantScheduleTrait;

    public function __invoke()
    {
        Log::info('risk-notification-send-command-triggered');
        if (tenant('id')) {
            $expired=$this->checkIfSubscriptionExpired(tenant('id'));
            if ($expired) {
                return false;
            }
            $this->SetUpTenantMailContent(tenant('id'));
        }

        Artisan::call('risk_notification:send');
    }
}
