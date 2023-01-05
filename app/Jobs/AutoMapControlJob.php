<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use App\Mail\Compliance\AssignTaskEmail;
use App\ScheduledTasks\TenantScheduleTrait;
use App\Traits\Compliance\AutoMapControl;

class AutoMapControlJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TenantScheduleTrait, AutoMapControl;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected $project;
    protected $project_to_map;

    public function __construct($project,$project_to_map)
    {
        $this->project = $project;
        $this->project_to_map = $project_to_map;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $this->linkImplementedControl($this->project_to_map,$this->project);
    }
}
