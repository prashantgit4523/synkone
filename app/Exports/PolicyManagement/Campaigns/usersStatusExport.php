<?php

namespace App\Exports\PolicyManagement\Campaigns;

use App\Models\GlobalSettings\GlobalSetting;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use App\Models\PolicyManagement\Campaign\CampaignAcknowledgment;
use App\Models\PolicyManagement\Campaign\CampaignActivity;
use App\Models\PolicyManagement\Campaign\Campaign;

class usersStatusExport implements FromCollection
{
    private $campaign;
    private $local;
    private $appTimezone;

    public function __construct($campaign, $local)
    {
        $this->campaign = $campaign;
        $this->local = $local;
        $this->appTimezone = GlobalSetting::first()->timezone;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $campaignActivities = $this->campaign->activities()->with('user', 'campaign.policies')->get();

        $campaignActivitiesCollection = [
            [
                'first_name',
                'last_name',
                'email',
                'department',
                'policies',
                'status',
                'acknowledge_date',
                'sent_date',
            ],
        ];

        foreach ($campaignActivities as $campaignActivity) {
            $policies = $campaignActivity->campaign->policies->pluck('display_name')->toArray();
            $acknowledgeDate = '';
            $sentDate = '';

            //count of pending policies of individual user
            $pendingAcknowledgmentsPerUser = CampaignAcknowledgment::where('campaign_id', $campaignActivity->campaign_id)
                ->where('user_id', $campaignActivity->user_id)
                ->where('status', 'pending')
                ->count();

            if ($campaignActivity->type == 'email-sent') {
                $sentDate = Carbon::createFromFormat('Y-m-d H:i:s', $campaignActivity->created_at, $this->appTimezone)
                    ->setTimezone($this->local)
                    ->format('d-m-Y H:i:s');
            }
            if ($campaignActivity->type == 'policy-acknowledged') {
                $acknowledgeDate = Carbon::createFromFormat('Y-m-d H:i:s', $campaignActivity->created_at, $this->appTimezone)
                    ->setTimezone($this->local)
                    ->format('d-m-Y H:i:s');

                //check if it is awareness campaign
                $checkAwarenessCampaign = Campaign::where('id',$campaignActivity->campaign_id)->pluck('campaign_type')->first();
                
                if($checkAwarenessCampaign != 'awareness-campaign' && $pendingAcknowledgmentsPerUser == 0){
                    $lastAcknowledgedActivityId = CampaignActivity::where('campaign_id',$campaignActivity->campaign_id)
                            ->where('user_id', $campaignActivity->user_id)
                            ->where('type','policy-acknowledged')
                            ->latest()
                            ->pluck('id')
                            ->first();
                    $policyStatus = $lastAcknowledgedActivityId == $campaignActivity->id ? 'all-policy(ies)-acknowledged' : 'policy(ies)-acknowledged';
                }else{
                    $policyStatus = $checkAwarenessCampaign == 'awareness-campaign' ? 'awareness-campaign-completed' : 'policy(ies)-acknowledged';
                }
            }else{
                $policyStatus = $campaignActivity->type;
            }

            if ($campaignActivity->type == 'clicked-link') {
                $previousActivities = CampaignActivity::where('id','<',$campaignActivity->id)
                                            ->where('type','policy-acknowledged')
                                            ->where('campaign_id', $campaignActivity->campaign_id)
                                            ->where('user_id', $campaignActivity->user_id)
                                            ->get();

                foreach($previousActivities as $prevActivity){
                    foreach($policies as $policy){
                        if (str_contains($prevActivity->activity, $policy)) { 
                            $policies = array_filter($policies, function($e) use ($policy) {
                                return ($e !== $policy);
                            });
                        }
                    }
                }
            }

            $campaignActivitiesCollection[] = [
                $campaignActivity->user->first_name,
                $campaignActivity->user->last_name,
                $campaignActivity->user->email,
                $campaignActivity->user->department,
                $campaignActivity->type == 'policy-acknowledged' ? str_replace(", ",",",str_replace(" policy(ies) are acknowledged","",$campaignActivity->activity)) : implode(',', $policies),
                $policyStatus,
                $acknowledgeDate,
                $sentDate,
            ];
        }

        return new Collection(
            $campaignActivitiesCollection
        );
    }
}
