<?php

namespace App\Console;

use App\Nova\Model\Tenant;
use App\ScheduledTasks\RiskManagement\RiskNotify;
use Illuminate\Console\Scheduling\Schedule;
use App\ScheduledTasks\AssetManagement\FetchAssets;
use App\Console\Commands\Kpi\UpdateKpiControlStatus;
use App\ScheduledTasks\Compliance\TaskDeadlineReminder;
use App\ScheduledTasks\Compliance\UnlockFrequencyTasks;
use App\ScheduledTasks\ThirdPartyRisk\EmailVendorProject;
use App\ScheduledTasks\Compliance\PassDueTasksResetStatus;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\ScheduledTasks\ThirdPartyRisk\FrequencyProjectUnlock;
use App\ScheduledTasks\ThirdPartyRisk\SendVendorProjectEmail;
use App\ScheduledTasks\PolicyManagement\SendAutoReminderEmail;
use App\ScheduledTasks\PolicyManagement\SendAcknowledgementEmail;
use App\ScheduledTasks\Integration\TechnicalControlImplementation;
use App\ScheduledTasks\Kpi\FetchKpiControlStatus;
use Illuminate\Support\Facades\App;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->call(new TaskDeadlineReminder())->name('campliance-task-deadline-reminder')->withoutOverlapping()->everyMinute();

        //run task schedule for compliance-pass-due-tasks-reset-status

        $schedule->call(new PassDueTasksResetStatus())->name('campliance-pass-due-tasks-reset-status')->withoutOverlapping()->everyMinute();

        // check the policy campaigns
//        $schedule->command('check:campaigns')->name('policy-CheckCampaigns')->withoutOverlapping()->everyMinute();

        //run task schedule for compliance-UnlockFrequencyTasks

        $schedule->call(new UnlockFrequencyTasks())->name('campliance-UnlockFrequencyTasks')->withoutOverlapping()->everyMinute();

        // Policy module
        $schedule->call(new SendAcknowledgementEmail())->name('policy-SendAcknowledgementEmail')->withoutOverlapping()->everyMinute();

        //run task schedule for policy-SendAutoReminderEmail
        $schedule->call(new SendAutoReminderEmail())->name('policy-SendAutoReminderEmail')->withoutOverlapping()->everyMinute();

        // Third party risk module
        $schedule->call(new FrequencyProjectUnlock())->name('third-party-risk-FrequencyProjectUnlock')->withoutOverlapping()->everyMinute();
        $schedule->call(new SendVendorProjectEmail())->name('third-party-risk-SendProjectEmail')->withoutOverlapping()->everyMinute();


        // $schedule->call(new TechnicalControlImplementation())->name('integration-TechnicalControlImplementation')->withoutOverlapping()->environments(['development', 'local'])->everyFiveMinutes();
        // $schedule->call(new TechnicalControlImplementation())->name('integration-TechnicalControlImplementation')->withoutOverlapping()->environments(['production'])->hourly();

        $schedule->call(new FetchAssets())->name('fetch-assets')->withoutOverlapping()->environments(['development', 'local'])->everyFiveMinutes();
        $schedule->call(new FetchAssets())->name('fetch-assets')->withoutOverlapping()->environments('production')->hourly();

        $schedule->call(new RiskNotify())
            ->name('risk-notification-send')
            ->withoutOverlapping()
            ->environments('local')
            ->everyMinute();

        $schedule->call(new RiskNotify())
            ->name('risk-notification-send')
            ->withoutOverlapping()
            ->environments(['development', 'production'])
            ->everyTenMinutes();

//            $schedule->call(new FetchKpiControlStatus())->name('fetch-kpi-control-status')->withoutOverlapping()->environments(['development', 'local'])->everyFiveMinutes();
        //    $schedule->call(new FetchKpiControlStatus())->name('fetch-kpi-control-status')->withoutOverlapping()->environments('production')->hourly();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
