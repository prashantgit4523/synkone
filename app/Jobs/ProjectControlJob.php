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

class ProjectControlJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TenantScheduleTrait;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    protected $email;
    protected $data;
    protected $subject;

    public function __construct($email,$data,$subject)
    {
        $this->email = $email;
        $this->data = $data;
        $this->subject = $subject;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if(tenant('id')){
            $this->SetUpTenantMailContent(tenant('id'),false);
        }
        Mail::to($this->email)->send(new AssignTaskEmail($this->data, $this->subject));
    }
}
