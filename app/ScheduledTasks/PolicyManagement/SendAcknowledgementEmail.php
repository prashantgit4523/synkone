<?php

namespace App\ScheduledTasks\PolicyManagement;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\ScheduledTasks\TenantScheduleTrait;
use App\Models\GlobalSettings\GlobalSetting;
use App\Mail\PolicyManagement\Acknowledgement;
use App\Models\PolicyManagement\Campaign\Campaign;
use App\Models\PolicyManagement\Campaign\CampaignActivity;
use App\Models\PolicyManagement\Campaign\CampaignAcknowledgmentUserToken;

class SendAcknowledgementEmail
{
    use TenantScheduleTrait;
    const CAMPAIGN_ACTIVITY_TABLE = 'policy_campaign_activities';

    public function __invoke()
    {
        if (tenant('id')) {
            $expired=$this->checkIfSubscriptionExpired(tenant('id'));
            if($expired){
                return false;
            }
            $this->SetUpTenantMailContent(tenant('id'));
        }
        $campaigns = Campaign::where('acknowledgement_email_sent', 0)->with('groups', 'groups.users', 'policies')->get();

        $appTimezone = GlobalSetting::first()->timezone;
        $now = Carbon::now($appTimezone);

        foreach ($campaigns as $campaign) {
            $nowDateTime = Carbon::now($campaign->timezone);
            $launchDate = Carbon::createFromFormat('Y-m-d H:i:s', $campaign->launch_date, 'UTC')->setTimezone($campaign->timezone);


            if ($launchDate->lessThanOrEqualTo($nowDateTime)) {
                $acknowledgements = $campaign->acknowledgements->where('status', 'pending');
                $policies = $campaign->policies;

                $totalEmailSentCount = 0;

                foreach ($acknowledgements->groupBy('user_id') as $index => $acknowledgementGroup) {
                    $user = $acknowledgementGroup->first()->user;

                    $acknowledgmentUserToken = CampaignAcknowledgmentUserToken::where('campaign_id', $campaign->id)->where('user_id', $user->id)->first();

                    if ($acknowledgmentUserToken) {
                        try {
                            Mail::to($user->email)->send(new Acknowledgement($acknowledgmentUserToken, $campaign, $acknowledgementGroup, $user));

                            // When email sent successfully
                            DB::table(self::CAMPAIGN_ACTIVITY_TABLE)->insert([
                                'campaign_id' => $campaign->id,
                                'activity' => 'Email Sent on Campaign start',
                                'type' => 'email-sent',
                                'user_id' => $user->id,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]);

                            ++$totalEmailSentCount;
                        } catch (\Exception $ex) {
                            echo $ex;
                            Log::info('mail_exception',['mail_ex'=>'mail exception on tenant id'.tenant('id')]);

                            DB::table(self::CAMPAIGN_ACTIVITY_TABLE)->insert([
                                'campaign_id' => $campaign->id,
                                'activity' => 'Error Sending Email',
                                'type' => 'email-sent-error',
                                'user_id' => $user->id,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ]);
                        }
                    }
                }

                // acknowledgement_email_sent column updating
                if ($totalEmailSentCount > 0) {
                    $campaign->acknowledgement_email_sent = 1;
                    $campaign->update();
                }
            }
        }
    }
}
