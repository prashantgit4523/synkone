<?php

namespace App\Http\Controllers\PolicyManagement\Campaign;

use Auth;
use App\Traits\HasSorting;
use Carbon\Carbon;
use Inertia\Inertia;
use App\Traits\Timezone;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Models\Compliance\Project;
use App\Models\Compliance\Evidence;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\PolicyManagement\Policy;
use Illuminate\Support\Facades\Storage;
use App\Models\Compliance\ProjectControl;
use App\Traits\DataScopeAccessCheckTrait;
use App\Mail\PolicyManagement\AutoReminder;
use App\Models\GlobalSettings\GlobalSetting;
use App\Models\PolicyManagement\Group\Group;
use App\Models\PolicyManagement\Group\GroupUser;
use App\Models\PolicyManagement\Campaign\Campaign;
use App\Http\Resources\PolicyManagement\CampaignResource;
use App\Models\PolicyManagement\Campaign\CampaignActivity;
use App\Exports\PolicyManagement\Campaigns\usersStatusExport;
use App\Models\Compliance\ComplianceProjectControlHistoryLog;
use App\Models\PolicyManagement\Campaign\CampaignAcknowledgment;
use App\Models\PolicyManagement\Campaign\CampaignAcknowledgmentUserToken;
use Illuminate\Support\Facades\DB;

class CampaignController extends Controller
{
    protected $viewBasePath = 'policy-management.campaign.';
    protected $authUser;
    use Timezone, DataScopeAccessCheckTrait, HasSorting;

    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware(function ($request, $next) {
            $this->authUser = Auth::guard('admin')->user();

            return $next($request);
        });
    }

    /**
     * Method index
     *
     * @param Request $request [explicite description]
     *
     * @return void
     */
    public function index(Request $request)
    {
        $timezones = $this->appTimezone();

        /* Sharing page title to view */
        view()->share('pageTitle', 'Campaigns - Policy Management');

        return Inertia::render('policy-management/campaign-page/CampaignPage', [
            'timezones' => $timezones
        ]);
    }

    public function campaignList(Request $request)
    {
        $campaignStatus = $request->campaign_status;
        $campaigns = Campaign::where(function ($query) use ($request, $campaignStatus) {
            if ($campaignStatus) {
                $query->whereIn('status', is_array($campaignStatus) ? $campaignStatus : [$campaignStatus]);
            }

            if ($request->campaign_name) {
                $query->where('name', 'like', '%' . $request->campaign_name . '%');
            }
        })->with('policies', 'groups')
            ->orderBy('id', 'desc')
            ->get();

        return CampaignResource::collection($campaigns)->additional(['success' => true]);
    }

    /**
     * Returns the campaign users.
     **/
    public function renderCampaignUsers(Request $request, $campaignId)
    {

        $campaign = Campaign::withoutGlobalScopes()->with('groups')->where('id', $campaignId)->first();


        if (!$campaign) {
            return response()->json([
                'success' => false,
            ]);
        }

        $campaignUsers = $campaign
            ->users()
            ->when($request->filled('sort_by'), function ($query) use ($request) {
                $this->sort(['first_name', 'last_name', 'email', 'department'], $query);
            })
            ->where(function ($query) {
                if (request('filter_by_user_name')) {
                    $query->whereRaw("concat(first_name, ' ', last_name) like '%" . request('filter_by_user_name') . "%' ");
                }
            })
            ->paginate($request->page_length);

        return response()->json([
            'success' => true,
            'data' => [
                'campaign' => $campaign,
                'campaignUsers' => $campaignUsers
            ]
        ]);
    }

    /**
     * Returns campaign user activities
     */
    public function getUserActivities(Request $request, $campaignId, $userId)
    {
        return CampaignActivity::where(
            [['campaign_id', $campaignId], ['user_id', $userId]]
        )->paginate($request->page_length);
    }


    /***
     * Creating the create campaigns
     */
    public function store(Request $request)
    {
        $request->validate([
            'data_scope' => 'required',
            'name' => 'required',
            'policies' => 'required|array|min:1|max:100',
            'launch_date' => 'required|date|after:' . date('Y/m/d'),
            'due_date' => 'nullable|date|after:launch_date',
            'timezone' => 'required',
            'groups' => 'required|array|min:1',
            'auto_enroll_users' => [
                'required',
                Rule::in(['yes', 'no']),
            ]
        ]);

        $input = $request->all();

        $newCampaign = \DB::transaction(function () use ($request, $input) {
            // formatting dates
            $launchDate = new \DateTime($input['launch_date']);
            $launchDate = $launchDate->format('Y-m-d H:i:s');

            $dueDate = new \DateTime($input['due_date']);
            $dueDate = $dueDate->format('Y-m-d H:i:s');

            // To seperate normal campaign from awareness campaign
            $campaignType = 'campaign';
            if (isset($input['campaign_type']) && $input['campaign_type'] == 'awareness') {
                $campaignType = 'awareness-campaign';
            }

            $campaign = Campaign::create([
                'name' => $input['name'],
                'owner_id' => $this->authUser->id,
                'launch_date' => $launchDate,
                'due_date' => $dueDate,
                'campaign_type' => $campaignType,
                'timezone' => $input['timezone'],
                'auto_enroll_users' => $request['auto_enroll_users'],
            ]);

            /* saving campaign policies */

            /* Getting the policies */
            $policies = Policy::whereIn('id', $request->policies)->get();

            /* Storing the campaign policies*/
            foreach ($policies as $policy) {
                $campaignPolicy = $campaign->policies()->create([
                    'policy_id' => $policy->id,
                    'display_name' => $policy->display_name,
                    'type' => $policy->type,
                    'path' => $policy->path,
                    'version' => $policy->type === 'automated' ? $policy->document_template->latest?->version : $policy->version,
                    'description' => $policy->description,
                ]);

                /* Storing the policy file */
                if ($policy->type == 'document') {
                    $policyFile = Storage::url('public/' . $policy->path);
                    $filePath = "policy-management/campaign-policies/{$campaignPolicy->id}";
                    $fileName = basename($policyFile);
                    // Store the Content
                    if (env('APP_ENV_REGION') == "KSA") {
                        $policyFile = Storage::get('public/' . $policy->path);
                        Storage::put('public/' . $filePath . '/' . $fileName, $policyFile);
                    } else {
                        Storage::copy('public/' . $policy->path, 'public/' . $filePath . '/' . $fileName);
                    }
                    $campaignPolicy->update([
                        'path' => $filePath . '/' . $fileName,
                    ]);
                }
            }

            $controlId = null;
            if (isset($input['control_id']) && $input['control_id'] != '') {
                $controlId = $input['control_id'];
            }

            foreach ($request->groups as $groupOrUser) {
                if (substr_count($groupOrUser, '-')) {
                    // it's a group user
                    $explodedGroupOrUser = explode("-", $groupOrUser);
                    $groupId = $explodedGroupOrUser[0];
                    $userId = $explodedGroupOrUser[1];
                    $group = Group::where('id', $groupId)->first();
                    $users = GroupUser::where('id', $userId)->get();
                } else {
                    // it's a group
                    $group = Group::where('id', $groupOrUser)->first();
                    $users = $group->users;
                    /* Saving campaign group */
                }
                /* Creating campaign groups*/
                $campaignGroup = $campaign->groups()->create([
                    'group_id' => $group ? $group->id : null,
                    'name' => $group ? $group->name : 'Deleted Group',
                ]);

                /* creating campaign group users >> users with same email are not created*/
                foreach ($users as $user) {
                    $alreadyAdded = $campaign->users()->where('email', $user->email)->first();
                    /*Going to next iteration when user already added*/
                    if ($alreadyAdded) {
                        continue;
                    }

                    $campaignGroupUser = $campaignGroup->users()->create([
                        'email' => $user->email,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name,
                        'department' => $user->department,
                    ]);

                    CampaignAcknowledgmentUserToken::create([
                        'campaign_id' => $campaign->id,
                        'user_id' => $campaignGroupUser->id,
                        'token' => encrypt($campaign->id . '-' . $campaignGroupUser->id . date('r', time())),
                    ]);

                    /* Creating Policy Acknowledgement tokens for users */
                    foreach ($campaign->policies as $policy) {
                        $token = encrypt($campaign->id . '-' . $policy->id . '-' . $campaignGroupUser->id);

                        CampaignAcknowledgment::create([
                            'campaign_id' => $campaign->id,
                            'policy_id' => $policy->id,
                            'user_id' => $campaignGroupUser->id,
                            'control_id' => $controlId,
                            'token' => $token,
                        ]);
                    }
                }
            }

            if ($controlId) {
                $deadline = new \DateTime($input['due_date']);
                $deadline = $deadline->format('Y-m-d');
                //assign same responsible to all control of that department
                $requestedControl = ProjectControl::select('responsible')->where('id', $controlId)->first();
                //updating responsible of current control to all awareness control
                ProjectControl::where('automation', 'awareness')
                    ->update([
                        'responsible' => $requestedControl->responsible,
                        'deadline'    => $deadline,
                        'is_editable' => false
                    ]);

                //assign responsible to all control of other department
                $project_controls = ProjectControl::withoutGlobalScopes()->where('automation', 'awareness')->get();
                if ($project_controls) {
                    $campaign_owner = $campaign->owner_id;
                    foreach ($project_controls as $project_control) {
                        if ($campaign_owner !== $project_control->responsible) {
                            $project_owner = Project::withoutGlobalScopes()->where('id', $project_control->project_id)->first()->admin_id;
                            $project_control->responsible = $project_owner;
                        }
                        $project_control->deadline = $deadline;
                        $project_control->is_editable = false;
                        $project_control->save();
                    }
                }
            }

            return $campaign;
        });

        if (!$newCampaign) {
            return response()->json([
                'success' => false,
                'message' => 'Oops something went wrong.',
            ]);
        }

        //sending campaigns  email right away without waiting for CRON
        // Artisan::call('schedule:run');

        // executing shell script without waiting for it
        callArtisanCommand('schedule:run');

        //For dublicate campaign response
        if ($request->duplicate_campaign_form) {
            Log::info('User has duplicated a campaign.', ['campaign_id' => $newCampaign->id]);
            return redirect()->back()->with([
                'message' => 'Campaign duplicated successfully.',
                'data' => $newCampaign
            ]);
        }

        //for add campaign response
        Log::info('User has created a campaign.', ['campaign_id' => $newCampaign->id]);
        return redirect()->back()->with([
            'success' => 'Campaign added successfully.',
            'data' => $newCampaign
        ]);
    }

    public function show(Request $request, $campaignId)
    {
        $app_timezone = GlobalSetting::query()->first('timezone')->timezone;
        $campaign = Campaign::withoutGlobalScopes()->with(['acknowledgements', 'groups', 'policies'])->findOrFail($campaignId);

        $this->checkDataScopeAccess($campaign);

        $acknowledgements = $campaign->acknowledgements;
        $campaignActivities = $campaign->activities;
        $emailSentSuccessOld = $campaignActivities->where('activity', 'Email Sent on Campaign start')->count();
        $emailSentSuccessNew = $campaignActivities->Where('activity', 'Email Sent on new user')->count();
        $emailSentSuccess = $emailSentSuccessOld + $emailSentSuccessNew;
        $emailSentFailaures = $campaignActivities->where('type', 'email-sent-error')->count();
        $totalEmailSent = ($emailSentSuccess + $emailSentFailaures);

        //checking if user has completed all the policy to acknowledged
        $totalAcknowledgements = $acknowledgements->groupBy('user_id')->count();
        $pendingAcknowledgements = $acknowledgements->where('status', 'pending')->groupBy('user_id')->count();
        $completedAcknowledgements = $totalAcknowledgements - $pendingAcknowledgements;
        $completedAcknowledgementsPercentage = ($completedAcknowledgements && $totalAcknowledgements) ? round($completedAcknowledgements * 100 / $totalAcknowledgements) : 0;


        // Data for Campaign Timeline
        $campaignTimeline = \DB::SELECT("SELECT 
                pca.id,
                concat ('<div class=`timline-item-content`>',FROM_UNIXTIME(UNIX_TIMESTAMP(pca.created_at), '%M %D,%Y %H:%i:%s'),'<br>event: ',pca.type,'<br>email: <b>',pcgu.email,'</b></div>') as title,
                FROM_UNIXTIME(UNIX_TIMESTAMP(pca.created_at), '%Y-%m-%d %H:%i:%s') as start,
                CASE
                    WHEN pca.type = 'email-sent' THEN 'email-sent bg-success'
                    WHEN pca.type = 'policy-acknowledged' THEN 'policy-acknowledged bg-warning'
                    WHEN pca.type = 'email-sent-error' THEN 'email-sent-error bg-danger'
                    WHEN pca.type = 'clicked-link' THEN 'clicked-link bg-primary'
                    ELSE concat(pca.type,' bg-secondary')
                END as className
            from policy_campaign_activities pca 
            left join policy_campaign_group_users pcgu on pca.user_id = pcgu.id
            where pca.campaign_id = ?
        ", [$campaignId]);

        $campaign['launch_date'] = Carbon::createFromFormat('Y-m-d H:i:s', $campaign->launch_date, 'UTC')->setTimezone($app_timezone);
        $campaign['due_date'] = Carbon::createFromFormat('Y-m-d H:i:s', $campaign->due_date, 'UTC')->setTimezone($app_timezone);

        return Inertia::render('policy-management/campaign-show-page/CampaignShowPage', [
            'campaignId' => $campaignId,
            'campaign' => $campaign,
            'totalAcknowledgements' => $totalAcknowledgements,
            'completedAcknowledgements' => $completedAcknowledgements,
            'completedAcknowledgementsPercentage' => $completedAcknowledgementsPercentage,
            'totalEmailSent' => $totalEmailSent,
            'emailSentSuccess' => $emailSentSuccess,
            'emailSentFailaures' => $emailSentFailaures,
            'campaignTimeline' => $campaignTimeline
        ]);
    }

    /**
     * Deleting the campaigns.
     **/
    public function delete(Request $request, $id)
    {
        try {
            DB::beginTransaction();
            $campaign = Campaign::findOrFail($id)->select('id', 'campaign_type')->first();
            //revert all awareness control to normal
            if ($campaign->campaign_type == 'awareness-campaign') {
                $allProjectControls = ProjectControl::withoutGlobalScopes()
                    ->where('automation', 'awareness')
                    ->select('id')
                    ->get()
                    ->pluck('id')
                    ->toArray();
                //update status
                DB::table('compliance_project_controls')
                    ->where('automation', 'awareness')
                    ->where('status', 'Implemented')
                    ->update([
                        'status'      => 'Not Implemented',
                        'deadline'    => now()->addDays(7)->format('Y-m-d'),
                        'is_editable' => true,
                        'frequency'   => 'One-Time'
                    ]);

                //delete evidence
                DB::table('compliance_project_control_evidences')
                    ->whereIn('project_control_id', $allProjectControls)
                    ->delete();
            }

            $deleted = DB::table('policy_campaigns')->where('id', $id)->delete();

            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'data' => [],
                    'message' => 'Oops something went wrong, please try again'
                ]);
            }
            Log::info('User has deleted a campaign.', ['campaign_id' => $id]);

            DB::commit();
            return response()->json([
                'success' => true,
                'data' => [],
                'message' => 'Campaign deleted successfully.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::info('Campaign.', ['campaign_id' => $id] . 'delete attempt by user failed because:' . $e->getMessage);
            return response()->json([
                'success' => false,
                'data' => [],
                'message' => 'Oops something went wrong, please try again'
            ]);
        }
    }

    /**
     * Sends policy acknowledgement reminder to campaign users.
     * **/
    public function sendUsersReminder(Request $request, $id)
    {
        $campaign = Campaign::findOrFail($id);

        $nowDateTime = new \DateTime('now', new \DateTimeZone($campaign->timezone));
        $campaignLaunchDate = new \DateTime($campaign->launch_date, new \DateTimeZone($campaign->timezone));

        if ($campaignLaunchDate->format('Y-m-d H:i:sP') > $nowDateTime->format('Y-m-d H:i:sP')) {
            return redirect()->back()->withError("Can't send reminder to a campaign which has not been started yet.");
        }

        $acknowledgements = $campaign->acknowledgements->where('status', 'pending');

        $acknowledgementGroups = $acknowledgements->groupBy('user_id');

        foreach ($acknowledgementGroups as $index => $acknowledgementGroup) {
            $user = $acknowledgementGroup->first()->user;
            $acknowledgmentUserToken = CampaignAcknowledgmentUserToken::where('campaign_id', $campaign->id)->where('user_id', $user->id)->first();

            try {
                Mail::to($user->email)->send(new AutoReminder($acknowledgmentUserToken, $campaign, $acknowledgementGroup, $user));

                CampaignActivity::create([
                    'campaign_id' => $campaign->id,
                    'activity' => 'Reminder Email Sent',
                    'type' => 'email-sent',
                    'user_id' => $user->id,
                ]);
            } catch (\Exception $exception) {
                CampaignActivity::create([
                    'campaign_id' => $campaign->id,
                    'activity' => 'Error Sending Email',
                    'type' => 'email-sent-error',
                    'user_id' => $user->id,
                ]);

                return redirect()->back()->with(['exception' => 'Failed to process request. Please check SMTP authentication connection.']);
            }
        }
        Log::info('User has sent policy acknowledgement reminder to users', ['campaign_id' => $id]);
        return redirect()->back()->with('success', 'Campaign Acknowledgment reminder emails sent to policy management users.');
    }

    public function getCampaignData(Request $request, $campaignId)
    {
        $campaign = Campaign::with('policies')->findOrFail($campaignId);

        return response()->json([
            'success' => true,
            'data' => $campaign,
        ]);
    }

    public function exportPdf(Request $request, $campaignId)
    {
        $campaign = Campaign::withoutGlobalScopes()->with('acknowledgements')->findOrFail($campaignId);

        $acknowledgements = $campaign->acknowledgements;
        $campaignActivities = $campaign->activities;
        $emailSentSuccessOld = $campaignActivities->where('activity', 'Email Sent on Campaign start')->count();
        $emailSentSuccessNew = $campaignActivities->Where('activity', 'Email Sent on new user')->count();
        $emailSentSuccess = $emailSentSuccessOld + $emailSentSuccessNew;
        $emailSentFailaures = $campaignActivities->where('type', 'email-sent-error')->count();
        $totalEmailSent = ($emailSentSuccess + $emailSentFailaures);
        $totalAcknowledgements = $acknowledgements->groupBy('user_id')->count();
        $pendingAcknowledgements = $acknowledgements->where('status', 'pending')->groupBy('user_id')->count();
        $completedAcknowledgements = $totalAcknowledgements - $pendingAcknowledgements;
        $completedAcknowledgementsPercentage = ($completedAcknowledgements && $totalAcknowledgements) ? round($completedAcknowledgements * 100 / $totalAcknowledgements) : 0;
        $data = [
            'campaign' => $campaign,
            'timezone' => $this->appTimezone()[$campaign->timezone],
            'acknowledgements' => $acknowledgements,
            'emailSentSuccess' => $emailSentSuccess,
            'emailSentFailaures' => $emailSentFailaures,
            'totalEmailSent' => $totalEmailSent,
            'totalAcknowledgements' => $totalAcknowledgements,
            'completedAcknowledgements' => $completedAcknowledgements,
            'completedAcknowledgementsPercentage' => $completedAcknowledgementsPercentage,
            'is_awareness' => $campaign->campaign_type == 'awareness-campaign' ? true : false
        ];

        // return view(  $this->viewBasePath.'campaign-pdf-export', $data   );

        $pdf = \PDF::loadView($this->viewBasePath . 'campaign-pdf-export', $data);
        $pdf->setOptions([
            'enable-local-file-access' => true,
            'enable-javascript' => true,
            'javascript-delay' => 3000,
            'enable-smart-shrinking' => true,
            'no-stop-slow-scripts' => true,
            'header-center' => 'Note: This is a system generated report',
            'footer-center' => $campaign->campaign_type == 'awareness-campaign' ? 'Native awareness - Campaign Report' : 'Policy Management - Campaign Report',
            'footer-left' => 'Confidential',
            'footer-right' => '[page]',
            'debug-javascript' => true,
        ]);

        if ($campaign->campaign_type != 'awareness-campaign') {
            Log::info('User has downloaded a campaign dashboard report as pdf.', ['campaign_id' => $campaignId]);

            return $pdf->download('campaign-dashboard-report.pdf');
        }
        $filename = 'Campaign Report ' . date('d-m-Y');

        return $pdf->inline($filename . '.pdf');
    }

    public function exportCsv(Request $request, $campaignId)
    {
        $campaign = Campaign::findOrFail($campaignId);
        Log::info('User has downloaded a campaign dashboard report as csv.', ['campaign_id' => $campaignId]);
        return Excel::download(new usersStatusExport($campaign, $request->local), 'campaign-users-status.csv');
    }

    public function getCampaignCreateData(Request $request)
    {
        if ($request->is_awareness) {
            $policies = Policy::where('type', 'awareness')->get();
        } else {
            $policies = Policy::query()
                ->whereIn('type', ['document', 'doculink'])
                ->orWhere(function ($query) {
                    $query->where('type', 'automated')
                        ->whereHas('document_template', function ($q) {
                            $q->whereHas('published');
                        });
                })
                ->get();
        }

        $groups = Group::query()->has('users')->with('users')->orderByName()->orderBy('id', 'DESC')->get();
        $groupUsers = GroupUser::orderByName()->get()->unique('email');
        $groupUsersArray = [];
        foreach ($groupUsers as $user) {
            array_push($groupUsersArray, $user);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'policies' => $policies,
                'groups' => $groups,
                'groupUsers' => $groupUsersArray
            ]
        ]);
    }

    public function completeCampaign(Request $request, $campaignId)
    {
        $updated = Campaign::where('id', $campaignId)->update(['status' => 'archived']);

        if (!$updated) {
            return response()->json([
                'success' => false,
                'data' => [],
                'message' => 'Oops somthing went wrong, please try again'
            ]);
        }
        Log::info('User has updated a campaign.', ['campaign_id' => $campaignId]);

        return response()->json([
            'success' => true,
            'data' => [],
            'message' => 'Campaign marked as complete successfully.'
        ]);
    }
}
