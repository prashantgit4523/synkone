<?php

namespace App\Console\Commands;

use App\Models\Compliance\Project;
use App\Models\Compliance\Standard;
use Illuminate\Console\Command;

class ComplianceFixCommands extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'compliance-controls:fix-and-merge';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command will run all compliance standards template control and compliance project control fixes and then merge';

    /**
     * Execute the console command.
     *
     * @return int
     */

    public function handle()
    {
        $this->info('Start of fix and merge');
        $this->info('Fixing project data');


        $standard = Standard::where('name', 'UAE IA')->withCount('controls')->first();
        $uae_projects = Project::where('standard', 'UAE IA')->withCount('controls')->get();
        $old_project = 0;
        foreach ($uae_projects as $project) {
            if($project->controls_count != '188'){
                $old_project++;
            }
        }
        if ($standard->controls_count != "188" || $old_project > 0) {
            $this->call("compliance-project-controls:update", [
                '--old' => true,
            ]);

            $this->info('Merging the controls in existing projects');
            $this->call('compliance-project-controls:merge');

            $this->info('Refreshing the standard templates, to have correct controls');
            $this->call('compliance-standards:refresh');

            $this->info('Refreshing the risk template mapping');
            $this->call('risk-template:refresh');

            $this->info('Fixed and merged.');
        }

        return 0;
    }
}
