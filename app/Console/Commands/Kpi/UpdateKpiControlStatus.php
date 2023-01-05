<?php

namespace App\Console\Commands\Kpi;

use App\Helpers\SystemGeneratedDocsHelpers;
use Illuminate\Console\Command;
use App\Models\Compliance\Project;
use App\Models\Compliance\StandardControl;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Integration\Integration;
use App\Models\Controls\KpiControlStatus;
use App\ScheduledTasks\TenantScheduleTrait;

class UpdateKpiControlStatus extends Command
{
    use TenantScheduleTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kpi_controls:update {integration_id?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command to update the kpi controls';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        KpiControlStatus::whereNotNull('id')->delete();

        // SystemGeneratedDocsHelpers::setKPIControlsNotImplemented();

        $this->info('updating kpi controls');

        if (tenant('id')) {
            $expired=$this->checkIfSubscriptionExpired(tenant('id'));
            if($expired){
                return false;
            }
            Log::info('Kpi update.', ['tenant_id' => tenant('id')]);
            $this->SetUpTenantMailContent(tenant('id'));
        }

        if (Project::doesntExist()) {
            return;
        }

        $integration_query = Integration::query()->where('connected', true);
        if ($this->argument("integration_id")) {
            $integration_query->where('id', $this->argument("integration_id"));
        }
        $integrations = $integration_query->get();

        foreach ($integrations as $integration) {
            $provider = $integration->provider;

            $actions = $provider->kpi_integration_controls->groupBy('action.action')->toArray();
            
            $key = 'integrations.' . $integration->slug;
            if (config()->has($key) && count($actions) > 0) {
                $class = str_replace("CustomProviders\\", "CustomProviders\\Kpi\\", config($key));
                if (class_exists($class)) {
                    $handler = new $class();
                    DB::beginTransaction();
                    foreach ($actions as $action => $controls) {
                        if (method_exists($handler, $action) && !empty($controls)) {
                            $response = null;
                            try {
                                $response = $handler->{$action}();
                            } catch (\Throwable $e) {
                                Log::error(sprintf('Something went wrong when running the KPI for %s using %s:', $provider?->name, $action));
                                Log::error($e->getMessage());
                            }

                            if ($response) {
                                foreach ($controls as $control) {
                                    $this->updateKpiStatus($response, $control);
                                }
                            }

                        }
                    }

                    $integration->refresh();

                    if ($integration->connected) {
                        DB::commit();
                        continue;
                    }

                    DB::rollBack();
                }
            }
        }

        SystemGeneratedDocsHelpers::checkSystemGeneratedDocuments();
    }

    /**
     * updating status of kpi
     */
    public function updateKpiStatus($response, $kpi_control)
    {
        $data = json_decode($response);

        $standard_control = StandardControl::where([
            ['standard_id', $kpi_control['standard_id']],
            ['primary_id', $kpi_control['primary_id']],
            ['sub_id', $kpi_control['sub_id']]
        ])->first();

        // deleting control if null
        if(!isset($data->total)){
            KpiControlStatus::where('control_id',$standard_control->id)->delete();
            return;
        }

        if ($data->total > 0 && $data->passed > 0) {
            $data->per = $data->passed / $data->total * 100;
        } else {
            $data->per = 0;
        }

        $project_exist = Project::where('standard_id', $kpi_control['standard_id'])->exists();

        if ($standard_control && $project_exist && $standard_control['automation'] === 'technical') {
            KpiControlStatus::updateOrCreate(
                [
                    'control_id' => $standard_control->id,
                ],
                [
                    'total' => $data->total,
                    'per' => $data->per,
                ],
            );
        }

    }
}

