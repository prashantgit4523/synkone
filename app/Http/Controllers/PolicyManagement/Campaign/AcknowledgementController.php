<?php

namespace App\Http\Controllers\PolicyManagement\Campaign;

use Inertia\Inertia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Mail;
use App\Models\PolicyManagement\Policy;
use App\Models\Compliance\ProjectControl;
use App\Mail\PolicyManagement\CampaignCompleted;
use App\Models\PolicyManagement\Campaign\Campaign;
use App\Models\PolicyManagement\Campaign\CampaignPolicy;
use App\Models\PolicyManagement\Campaign\CampaignActivity;
use App\Models\Compliance\ComplianceProjectControlHistoryLog;
use App\Models\PolicyManagement\Campaign\CampaignAcknowledgment;
use App\Models\PolicyManagement\Campaign\CampaignAcknowledgmentUserToken;

class AcknowledgementController extends Controller
{
    protected $viewBasePath = 'policy-management.campaign.acknowledgement';

    public function show(Request $request, $token)
    {
        $campaignAcknowledgmentUserToken = CampaignAcknowledgmentUserToken::where('token', $token)->with(['user', 'campaign'])->first();

        if (!$campaignAcknowledgmentUserToken) {
            $message = 'This link is not valid anymore, you can safely close the page.';
            return view('errors.custom',compact('message'));
        }

        $userId = $campaignAcknowledgmentUserToken->user_id;
        $campaignId = $campaignAcknowledgmentUserToken->campaign_id;
        $campaignAcknowledgments = CampaignAcknowledgment::where('campaign_id', $campaignAcknowledgmentUserToken->campaign_id)
            ->where('user_id', $userId)
                ->where('status', 'pending')
                ->with(['user', 'policy'])
                // ->orderBy('id','desc')
                    ->paginate(10);
        if ($campaignAcknowledgments->count() == 0) {
            $user = $campaignAcknowledgmentUserToken->user;

            return Inertia::render('policy-management/campaign-policy-acknowledgement/acknowledged/AcknowledgedPage', [
                'user' => $user
            ]);
        }

        CampaignActivity::create([
            'campaign_id' => $campaignId,
            'activity' => 'Acknowledgment link clicked',
            'type' => 'clicked-link',
            'user_id' => $userId,
        ]);
        $pagination_data= [
            'current_page'=>$campaignAcknowledgments->currentPage(),
            'last_page'=>$campaignAcknowledgments->lastPage(),
            'total'=>$campaignAcknowledgments->total(),
            'per_page'=>$campaignAcknowledgments->perPage()
        ];

        if(isset($campaignAcknowledgmentUserToken->campaign) && $campaignAcknowledgmentUserToken->campaign->campaign_type == 'awareness-campaign')
        {
            $video_path = Policy::where('type','awareness')->first();
            return Inertia::render('policy-management/campaign-policy-acknowledgement/show/ShowAwarenessPage', [
                'campaignAcknowledgments' => $campaignAcknowledgments->items(),
                'campaignAcknowledgmentUserToken' => $campaignAcknowledgmentUserToken,
                'link_path' => $video_path->path
            ]);
        }
        else
        {
            return Inertia::render('policy-management/campaign-policy-acknowledgement/show/ShowPage', [
                'first_render'=> $request->page ? false : true,
                'paginationData' => $pagination_data,
                'campaignAcknowledgments' => $campaignAcknowledgments->items(),
                'campaignAcknowledgmentUserToken' => $campaignAcknowledgmentUserToken
            ]);
        }
    }

    public function confirm(Request $request)
    {
        $request->validate([
            'campaign_acknowledgment_user_token' => 'required',
            'agreed_policy' => 'required|array|min:1',
        ], [
            'agreed_policy.required' => 'Please click the checkbox to agree to the above policy.',
        ]);

        $campaignAcknowledgmentUserToken = CampaignAcknowledgmentUserToken::where('token', $request->campaign_acknowledgment_user_token)->first();

        if (!$campaignAcknowledgmentUserToken) {
            abort(404);
        }

        $totalPendingCampaignAcknowledgments = CampaignAcknowledgment::where('campaign_id', $campaignAcknowledgmentUserToken->campaign_id)
            ->where('user_id', $campaignAcknowledgmentUserToken->user_id)
                ->where('status', 'pending')
                    ->count();

        $campaignAcknowledgments = CampaignAcknowledgment::where('campaign_id', $campaignAcknowledgmentUserToken->campaign_id)
            ->where('user_id', $campaignAcknowledgmentUserToken->user_id)
                ->whereIn('token', $request->agreed_policy)
                ->with('policy')
                    ->get();

        if ($campaignAcknowledgments->count() == 0) {
            abort(404);
        }
        $projectControlsHistroyLog = [];
        $result = \DB::transaction(function () use ($campaignAcknowledgmentUserToken, $campaignAcknowledgments, $request) {
            $acknowledgedPolicies = [];

            foreach ($campaignAcknowledgments as $key => $campaignAcknowledgment) {
                $campaignAcknowledgment->status = 'completed';
                $campaignAcknowledgment->token = null;
                $campaignAcknowledgment->update();

                $acknowledgedPolicies[] = $campaignAcknowledgment->policy->display_name;
            }

            // Creating campaign activity
            CampaignActivity::create([
                'campaign_id' => $campaignAcknowledgmentUserToken->campaign_id,
                'activity' => implode(', ', $acknowledgedPolicies).' policy(ies) are acknowledged',
                'type' => 'policy-acknowledged',
                'user_id' => $campaignAcknowledgmentUserToken->user_id,
            ]);

            // Checking campaign is completed
            $pendingAcknowledgments = CampaignAcknowledgment::where('campaign_id', $campaignAcknowledgmentUserToken->campaign_id)
                ->where('status', 'pending')->count();

            $campaign = Campaign::findOrFail($campaignAcknowledgmentUserToken->campaign_id);
            if ($pendingAcknowledgments == 0) {

                // archiving the campaign
                $campaign->status = 'archived';
                $campaign->update();

                Mail::to($campaign->owner->email)->send(new CampaignCompleted($campaign));
            }

            //Implementing control from where awareness campaign was run
            if (isset($campaign) && $campaign->campaign_type === 'awareness-campaign') {
                $project_controls = ProjectControl::where('automation', 'awareness')->get();
                if ($project_controls) {
                    DB::transaction(function () use ($project_controls,$campaignAcknowledgmentUserToken) {
                        foreach ($project_controls as $project_control) {
                            $campaign = Campaign::findOrFail($campaignAcknowledgmentUserToken->campaign_id)->latest()->first()->owner_id;
                            if ($campaign !== $project_control->responsible) {
                                $project_control->responsible = $project_control->project->admin_id;
                            }
                            $project_control->status = "Implemented";
                            $project_control->frequency = "One-Time";
                            $project_control->is_editable = false;
                            $projectControlsHistroyLog[] = $project_control;
                            $project_control->save();
                        }
                    });
                }
            }
        });

        if($result){
            ComplianceProjectControlHistoryLog::insert($projectControlsHistroyLog);
        }


        $user = $campaignAcknowledgments->first()->user;

        $acknowledgement_completed=$totalPendingCampaignAcknowledgments == $campaignAcknowledgments->count();

        return redirect()->route('policy-management.campaigns.acknowledgement.completed')->with([
            'data' => [
                'campaignAcknowledgments' => $campaignAcknowledgments,
            'user' => $user,
            'acknowledgement_completed' => $acknowledgement_completed,
            'previous_url' => explode("?", $_SERVER['HTTP_REFERER'])[0]
            ]
        ]);
    }

    public function showCompletedPage(Request $request)
    {
        $data = $request->session()->has('data') ? $request->session()->get('data'): [];
        return Inertia::render('policy-management/campaign-policy-acknowledgement/completed/CompletedPage', $data);
    }

    public function getNewUrlForS3Policy(Request $request){
        $campaignAcknowledgmentUserToken = CampaignAcknowledgmentUserToken::where('token', $request->token)->with(['user', 'campaign'])->first();

        if (!$campaignAcknowledgmentUserToken) {
            abort(404);
        }

        $userId = $campaignAcknowledgmentUserToken->user_id;
        $campaignAcknowledgments = CampaignAcknowledgment::where('campaign_id', $campaignAcknowledgmentUserToken->campaign_id)
            ->where('policy_id',$request->policy_id)
            ->where('user_id', $userId)
                ->where('status', 'pending')
                    ->with(['user', 'policy'])
                        ->first();
        return response()->json($campaignAcknowledgments);
    }
}
