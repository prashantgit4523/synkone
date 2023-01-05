<?php

namespace App\Console\Commands;

use App\Models\Compliance\Project;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\ReportCategoryControl;
use App\Models\Compliance\ProjectControl;

class SecurityReportCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'security_report:check {--data_scope=1-0 : The data scope argument to get controls.}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks & updates security report controls status.';

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
        $dataScope = explode('-', $this->option('data_scope'));

        $organizationId = $dataScope[0];
        $departmentId = $dataScope[1];

        $securityControls = ReportCategoryControl::all();

        foreach ($securityControls as $securityControl) {
            $reportData = json_decode($securityControl->control_id, true);

            foreach ($reportData as $data) {
                $complianceControlIsEmpty = false;
                $standard_id = array_keys($data)[0];
                $control_id = array_values($data)[0];

                $projects = Project::query()->whereHas('scope', function ($query) use ($organizationId, $departmentId) {
                    $query->where('organization_id', $organizationId);
                    if ($departmentId == 0) {
                        $query->whereNull('department_id');
                    } else {
                        $query->where('department_id', $departmentId);
                    }
                })->where('standard_id', $standard_id)->get();

                foreach ($projects as $index => $project) {
                    $complianceControl = $project->implementedControls()->where(DB::raw('CONCAT(primary_id, id_separator, sub_id)'), $control_id)->get();

                    if ($complianceControl->count()) {
                        $automation = $complianceControl->where('automation', 'technical')->count() > 0 ? 'technical' : 'none';

                        $securityControl->update(['status' => 1, 'automation' => $automation]);
                        $this->info(sprintf('Control [%s] is compliant.', $control_id));

                        continue 3;
                    }

                    //check if until last item this conditions run
                    if($index === (count($projects) - 1)){
                        $complianceControlIsEmpty = true;
                    }
                }

                if(($projects->count() === 0 || $complianceControlIsEmpty) && $securityControl->status === 1){
                        $securityControl->update(['status' => 0, 'automation' => null]);
                        $this->info(sprintf('Control [%s] is not compliant.', $control_id));
                }
            }
        }
    }
}
