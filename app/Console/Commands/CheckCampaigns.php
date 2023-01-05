<?php

namespace App\Console\Commands;

use App\Models\PolicyManagement\Campaign\Campaign;
use App\Models\PolicyManagement\Campaign\CampaignAcknowledgmentUserToken;
use App\ScheduledTasks\TenantScheduleTrait;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CheckCampaigns extends Command
{
    use TenantScheduleTrait;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:campaigns';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks the policy campaigns status';

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
        if(tenant('id')){
            $expired=$this->checkIfSubscriptionExpired(tenant('id'));
            if($expired){
                return false;
            }
        }

        DB::transaction(function () {
            // grab the overdue policy campaigns (non-awareness)
            $policy_campaigns = Campaign::query()
                ->where('status', 'active')
                ->where('campaign_type', 'campaign')
                ->where('due_date', '<', now())
                ->pluck('id');

            // delete the old tokens (this prevents the access once archived)
            CampaignAcknowledgmentUserToken::query()
                ->whereIn('campaign_id', $policy_campaigns)
                ->delete();

            // set the status to archived (complete)
            Campaign::query()
                ->whereIn('id', $policy_campaigns)
                ->update([
                    'status' => 'archived'
                ]);

            // grab the overdue awareness campaigns
            $awareness_campaigns = Campaign::query()
                ->where('status', 'active')
                ->where('campaign_type', 'awareness-campaign')
                ->where('due_date', '<', now())
                ->pluck('id');

            // set the status to overdue
            Campaign::query()
                ->whereIn('id', $awareness_campaigns)
                ->update([
                    'status' => 'overdue'
                ]);
        });

        return 0;
    }
}
