<?php

namespace App\ScheduledTasks\Kpi;

use GuzzleHttp\Client;
use App\Models\Compliance\Project;
use Illuminate\Support\Facades\Log;
use App\Models\Integration\Integration;
use Illuminate\Support\Facades\Artisan;
use App\Models\Controls\KpiControlStatus;
use App\ScheduledTasks\TenantScheduleTrait;
use App\Models\Controls\KpiControlApiMapping;
use App\Models\Integration\IntegrationProvider;
use App\Traits\Integration\IntegrationApiTrait;

class FetchKpiControlStatus
{
    use IntegrationApiTrait,TenantScheduleTrait;

    public function __invoke()
    {
       Log::info('kpi-update-command-triggered');

       if(tenant('id')){
        Log::info('kpi-update-command-triggered for tenant:'.tenant('id'));
       }

       Artisan::call('kpi_controls:update');
    }

}