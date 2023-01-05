<?php

namespace App\Console\Commands\Integration;

use App\Models\Compliance\ProjectControl;
use App\Models\Integration\IntegrationAction;
use App\Models\Integration\IntegrationControl;
use App\Models\Integration\IntegrationProvider;
use Illuminate\Console\Command;
use App\Models\Compliance\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Integration\Integration;
use App\ScheduledTasks\TenantScheduleTrait;
use App\Traits\Integration\IntegrationApiTrait;
use App\Models\Compliance\StandardControl;
use App\Models\Compliance\ComplianceProjectControlHistoryLog;

class MapApiToControls extends Command
{

    use IntegrationApiTrait, TenantScheduleTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'technical-control:api-map {integration_id?}';

    const NOT_IMPLEMENTED = "Not Implemented";
    private const DATETIME_FORMAT = 'Y-m-d H:i:s';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'map api calls to project controls';

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
        $this->info('mapping technical automation api\'s to project controls');

        if (tenant('id')) {
            $expired=$this->checkIfSubscriptionExpired(tenant('id'));
            if($expired){
                return false;
            }
            Log::info('Api automation.', ['tenant_id' => tenant('id')]);
            $this->SetUpTenantMailContent(tenant('id'));
        }

        if (Project::doesntExist()) {
            return;
        }

        /*
         * Flow:
         * 1- Grab all the connected integrations
         * 2- Run the actions and store the response in an array
         * 3- Go through all the integration con
         * */

        $integration_providers = IntegrationProvider::query()
            ->whereHas('integration', function ($q) {
                $q->where('connected', true);
            })
            ->with(['integration_actions', 'integration_actions.integration_controls'])
            ->get();

        /*
         * [
         *  providerName => [
         *      actionName => [
         *          compliant => true/false
         *          response => json response
         *          ]
         *     ]
         * ]
         * */
        $data = [];

        foreach ($integration_providers as $provider) {
            $results = $this->runProvider($provider);
            $data[$provider->name] = $results;
        }

        foreach ($integration_providers as $provider) {
            foreach ($provider->integration_actions as $action) {
                if (array_key_exists($action->action, $data[$provider->name])) {
                    $action->integration_controls()->updateExistingPivot($action->integration_controls()->allRelatedIds(), $data[$provider->name][$action->action]);
                }
            }
        }

        DB::transaction(function () {
            $integration_controls = IntegrationControl::query()
                ->whereHas('integration_actions.integration_provider.integration', fn($q) => $q->where('connected', true))
                ->with(['integration_actions', 'integration_actions.integration_provider', 'integration_actions.integration_provider.integration'])
                ->get();

            foreach ($integration_controls as $control) {
                StandardControl::query()
                    ->where('standard_id', $control->standard_id)
                    ->where('primary_id', $control->primary_id)
                    ->where('sub_id', $control->sub_id)
                    ->update(['automation' => 'technical']);

                ProjectControl::query()
                    ->whereHas('project.of_standard', fn($q) => $q->where('id', $control->standard_id))
                    ->where('primary_id', $control->primary_id)
                    ->where('sub_id', $control->sub_id)
                    ->where('manual_override', 0)
                    ->where('status', '<>', 'Implemented')
                    ->where('applicable', true)
                    ->update(['automation' => 'technical']);

                $should_implement = $control
                    ->integration_actions()
                    ->wherePivot('is_compliant', false)
                    ->wherePivot('is_compliant', '<>', null)
                    ->doesntExist();

                ProjectControl::query()
                    ->whereHas('project.of_standard', fn($q) => $q->where('id', $control->standard_id))
                    ->where('primary_id', $control->primary_id)
                    ->where('sub_id', $control->sub_id)
                    ->where('automation', 'technical')
                    ->where('manual_override', 0)
                    ->where('applicable', true)
                    ->each(function ($control) use ($should_implement) {
                        if ($should_implement) {
                            $control->deadline = $control->status === self::NOT_IMPLEMENTED ? date('Y-m-d') : $control->deadline;
                        } else {
                            $control->deadline = $control->status === "Implemented" ? date('Y-m-d', strtotime('+7 days')) : $control->deadline;
                        }
                        $control->deadline = $control->status === self::NOT_IMPLEMENTED && $should_implement ? date('Y-m-d') : $control->deadline;
                        $control->status = $should_implement ? 'Implemented' : self::NOT_IMPLEMENTED;
                        $control->frequency = 'One-Time';
                        $control->responsible = $control->responsible ?? $control->project?->admin_id;
                        $control->approver = null;
                        $control->is_editable = !$should_implement;
                        $control->save();

                        // Update data in compliance_project_controls_history_log also
                        $projectCtonrolHistory = $control->controlHistory->last();
                        
                        $changeLogData = [
                            'project_id' => $control->project_id,
                            'control_id' => $control->id,
                            'applicable' => $control->applicable,
                            'log_date' => date('Y-m-d'),
                            'control_created_date' => $control->created_at,
                            'status' => $control->status,
                            'deadline' => $control->deadline,
                            'frequency' => $control->frequency
                        ];
                        if ($projectCtonrolHistory->log_date == date('Y-m-d')) {
                            $changeLogData['updated_at'] = date(self::DATETIME_FORMAT);
                            $projectCtonrolHistory->update($changeLogData);
                        } else {
                            $changeLogData['created_at'] = date(self::DATETIME_FORMAT);
                            $changeLogData['updated_at'] = date(self::DATETIME_FORMAT);
                            ComplianceProjectControlHistoryLog::create($changeLogData);
                        }
                    });
            }
        });
    }

    private function runProvider($provider)
    {
        $integration = $provider->integration;
        $results = [];

        $key = 'integrations.' . $integration->slug;
        if (config()->has($key)) {
            $class = config($key);
            $handler = new $class();

            foreach ($provider->integration_actions as $action) {
                $action_name = $action->action;
                if (method_exists($handler, $action_name)) {
                    $response = null;
                    try {
                        $response = $handler->{$action_name}();
                    } catch (\Throwable $e) {
                        Log::error(sprintf('Something went wrong when trying to run %s on %s:', $action_name, $provider?->name));
                        Log::error($e->getMessage());
                    }

                    $is_compliant = !empty(json_decode($response));

                    if (!array_key_exists($action_name, $results)) {
                        $results[$action_name] = [];
                    }

                    $results[$action_name] = [
                        'last_response' => !$is_compliant ? null : str_replace(['\n', '&nbsp;', '&rdquo;', '&ldquo;'], ['', ' ', "'", "'"], strip_tags($response)),
                        'is_compliant' => $is_compliant
                    ];
                }
            }
        }

        return $results;
    }
}
