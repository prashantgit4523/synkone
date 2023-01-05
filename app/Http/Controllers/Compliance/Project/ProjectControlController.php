<?php

namespace App\Http\Controllers\Compliance\Project;

use App\Helpers\SystemGeneratedDocsHelpers;
use App\Models\Compliance\StandardControl;
use App\Models\Integration\IntegrationControl;
use App\Traits\Compliance\ComplianceHelpers;
use App\Traits\HasSorting;
use Notification;
use Carbon\Carbon;
use Inertia\Inertia;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Jobs\ProjectControlJob;
use App\Utils\RegularFunctions;
use App\Models\Compliance\Comment;
use App\Models\Compliance\Project;
use App\Models\DataScope\Scopable;
use Illuminate\Support\Facades\DB;
use App\Models\Compliance\Evidence;
use App\Models\Compliance\Standard;
use App\Models\DataScope\DataScope;
use App\Utils\AccessControlHelpers;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\UserManagement\Admin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Models\PolicyManagement\Policy;
use Illuminate\Support\Facades\Storage;
use App\Models\Compliance\Justification;
use App\Models\Compliance\ProjectControl;
use App\Rules\Compliance\AllowedEvidence;
use App\Rules\ValidateUrlOrNetworkFolder;
use App\Traits\DataScopeAccessCheckTrait;
use App\Notifications\RemoveTaskNotification;
use App\Notifications\AssignedTaskNotification;
use App\Mail\Compliance\ControlAssignmentRemoval;
use App\Models\DocumentAutomation\DocumentTemplate;
use App\Models\Administration\OrganizationManagement\Department;
use App\Models\TaskScheduleRecord\ComplianceProjectTaskScheduleRecord;
use App\Models\PolicyManagement\Campaign\Campaign;
use App\Models\PolicyManagement\Campaign\CampaignPolicy;
use App\Models\PolicyManagement\Campaign\CampaignAcknowledgment;
use App\Models\Integration\Integration;

class ProjectControlController extends Controller
{
    protected $loggedUser;
    protected $viewBasePath = 'compliance.projects.';
    protected $globalAdmin = 'Global Admin';

    use DataScopeAccessCheckTrait;
    use ComplianceHelpers;
    use HasSorting;
    private $globalComplianceAdminRoles;
    private $notImplemented;
    private $underReview;
    private $dateFormat;
    private $somethingWrong;
    private $emailContentHello;
    private $emailContentProjectName;
    private $emailContentStandard;
    private $emailContentControlID;
    private $emailContentControlName;
    private $gotoTaskDetails;
    private $removalTaskAssignment;
    private $removalTaskAssignmentMessage;
    private $removalTaskAssignmentAction;
    private $removalApprovalResponsibility;
    private $mailSubject;
    private $mailTitleContent;
    private $mailInformationContent;
    private $newTaskAssignment;
    private $goToDashboard;
    private $policyAdministratorRoles;
    private $complianceAdministratorRoles;

    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware(function ($request, $next) {
            $this->loggedUser = Auth::guard('admin')->user();

            return $next($request);
        });

        \View::share('statuses', RegularFunctions::getProjectStatus());
        \View::share('frequencies', RegularFunctions::getFrequency());
        \View::share('contributors', RegularFunctions::getControlContributorList());
        $this->globalComplianceAdminRoles = [$this->globalAdmin, 'Compliance Administrator'];
        $this->notImplemented = 'Not Implemented';
        $this->underReview = 'Under Review';
        $this->dateFormat = 'j M Y';
        $this->somethingWrong = 'Oops something went wrong!';
        $this->emailContentHello = 'Hello ';
        $this->emailContentProjectName = '<b style="color: #000000;">Project Name: </b> ';
        $this->emailContentStandard = '<b style="color: #000000;">Standard: </b> ';
        $this->emailContentControlID = '<b style="color: #000000;">Control ID: </b> ';
        $this->emailContentControlName = '<b style="color: #000000;">Control Name: </b> ';
        $this->gotoTaskDetails = 'Go to task details';
        $this->removalTaskAssignment = 'Removal of task assignment';
        $this->removalTaskAssignmentMessage = 'You have been removed responsibility for providing evidence for the following tasks:';
        $this->removalTaskAssignmentAction = "This is an informational email and you don't have to take any action.";
        $this->removalApprovalResponsibility = 'Removal of approval responsibility';
        $this->mailSubject = 'Assignment as control approver';
        $this->mailTitleContent = 'You have been assigned as an approver for a new task. Please find the details below:';
        $this->mailInformationContent = "You don't have to take any action now. You'll get another email when your approval is required.";
        $this->newTaskAssignment = 'New task assignment';
        $this->goToDashboard = 'Go to my dashboard';
        $this->policyAdministratorRoles = [$this->globalAdmin, 'Policy Administrator'];
        $this->complianceAdministratorRoles = [$this->globalAdmin, 'Compliance Administrator', 'Contributor'];
    }

    public function index(Request $request, Project $project, $tab = 'Details')
    {
        /* Access control */
        if (!$this->loggedUser->hasAnyRole($this->globalComplianceAdminRoles)) {
            $assignedProjectCount = Project::where('id', $project->id)->whereHas('controls', function ($q) {
                $q->where('approver', $this->loggedUser->id);
                $q->orWhere('responsible', $this->loggedUser->id);
            })->count();

            if ($assignedProjectCount == 0) {
                return RegularFunctions::accessDeniedResponse();
            }
            $control_disabled = true;
        } else {
            $control_disabled = false;
        }
        $controls = [];
        $data = [];
        $data['total'] = $project->controls()->count();
        $data['notApplicable'] = $project->controls()->where('applicable', 0)->count();
        $data['implemented'] = $project->controls()->where('applicable', 1)->where('status', 'Implemented')->count();
        $data['notImplementedcontrols'] = $project->controls()->where('applicable', 1)->where('status', $this->notImplemented)->count();
        $data['rejected'] = $project->controls()->Where('status', 'Rejected')->count();
        $data['notImplemented'] = $data['notImplementedcontrols'] + $data['rejected'];
        $data['underReview'] = $project->controls()->where('applicable', 1)->where('status', $this->underReview)->count();
        $data['perImplemented'] = ($data['total'] > 0) ? ($data['implemented'] / $data['total']) * 100 : 0;
        $data['perUnderReview'] = ($data['total'] > 0) ? ($data['underReview'] / $data['total']) * 100 : 0;
        $data['perNotImplemented'] = ($data['total'] > 0) ? ($data['notImplemented'] / $data['total']) * 100 : 0;
        return Inertia::render('compliance/project-details/ProjectDetails', ['project' => $project, 'controls' => $controls, 'data' => $data, 'control_disabled' => $control_disabled, 'tab' => ucfirst($tab)]);
    }


    private function getProjectControlAssignableUsers($project)
    {
        $projectDepart = $project->department;
        $departmentIds = [];

        if (is_null($projectDepart->department_id)) {
            $departments = Department::with(['departments' => function ($query) {
                $query->where('parent_id', 0);
            }])->get();
        } else {
            $departments = Department::where('id', $projectDepart->department_id)->with(['departments' => function ($query) use ($projectDepart) {
                $query->where('parent_id', $projectDepart->department_id);
            }])->get();
        }


        foreach ($departments as $key => $department) {
            $departmentIds[] = $department->id;

            $departmentIds = array_merge($departmentIds, $department->getAllChildDepartIds());
        }

        return Admin::where('status', 'active')->whereHas('department', function ($q) use ($departmentIds, $projectDepart) {
            $q->whereIn('department_id', $departmentIds);

            /* In case of top organization */
            if (is_null($projectDepart->department_id)) {
                $q->orWhereNull('department_id');
            }
        })->get();

    }

    /**
     * Project Controls.
     **/
    public function Controls(Request $request, Project $project)
    {
        $count = 0;
        $render = [];
        $draw = [];

        // filtring control for only loging user
        if (!$this->loggedUser->hasAnyRole($this->globalComplianceAdminRoles)) {
            $approverControls = $project->controls()->where('approver', $this->loggedUser->id)->get();
            $responsibleControls = $project->controls()->where('responsible', $this->loggedUser->id)->get();
            $controls = $approverControls->merge($responsibleControls);
            $count = $controls->count();
        } else {
            $projectControlsQuery = $project->controls();
            $count = $projectControlsQuery->count();
            $controls = $projectControlsQuery->paginate($request->per_page ? $request->per_page : 10);
        }

        $disabled = false;
        if (!$this->loggedUser->hasAnyRole($this->globalComplianceAdminRoles)) {
            $disabled = true;
        }

        $contributors = $this->getProjectControlAssignableUsers($project);

        // dd($contributors);
        //List of contributors
        $finalData = [];
        $finalData['responsibleUsers']['availableUsers'][] = ["value" => 0, "label" => "Select Responsible"];
        $finalData['approverUsers']['availableUsers'][] = ["value" => 0, "label" => "Select Approver"];

        foreach ($controls as $control) {

            $finalData['responsibleUsers']['default'] = ["value" => 0, "label" => "Select Responsible"];
            $finalData['approverUsers']['default'] = ["value" => 0, "label" => "Select Approver"];

            $action = route('compliance-project-control-show', [$project->id, $control->id]);

            switch ($control->status){
				case $control->status == $this->notImplemented:
					$status = "<span class='badge task-status-red w-60 '>" . $control->status . '**</span>'; break;
				case $control->status == 'Implemented':
					$status = "<span class='badge task-status-green w-60'>" . $control->status.'</span> '; break;
				case $control->status == 'Rejected':
					$status = "<span class='badge task-status-orange w-60'>" . $control->status . '</span>'; break;
				default:
                    $status = "<span class='badge task-status-blue w-60'>" . $control->status . '</span>'; break;
			}

            $disabled = false;
            if (!$control->is_editable) {
                $disabled = true;
            }

            foreach ($contributors as $key => $contributor) {
                $contributorId = $contributor->id;
                $contributorName = ucwords($contributor->first_name . ' ' . $contributor->last_name);
                $finalData['responsibleUsers']['availableUsers'][$contributorId] = ["value" => $contributorId, "label" => $contributorName];
                $finalData['approverUsers']['availableUsers'][$contributorId] = ["value" => $contributorId, "label" => $contributorName];
                if ($contributorId == $control->responsible) {
                    $finalData['responsibleUsers']['default'] = ["value" => $contributorId, "label" => $contributorName];
                }
                if ($contributorId == $control->approver) {
                    $finalData['approverUsers']['default'] = ["value" => $contributorId, "label" => $contributorName];
                }
            }

            $deadline = $control->deadline == null ? date('Y-m-d') : $control->deadline;

            $frequencies = RegularFunctions::getFrequency();

            $frequencyData = [];
            $frequencyData['isDisabled'] = $disabled;
            $frequencyData['defaultValue'] = ["value" => "One-Time", "label" => "One-Time"];
            foreach ($frequencies as $freq) {
                if ($freq == $control->frequency) {
                    $frequencyData['defaultValue'] = ["value" => $freq, "label" => $freq];
                    $frequencyData['options'][] = ["value" => $freq, "label" => $freq];
                } else {
                    $frequencyData['options'][] = ["value" => $freq, "label" => $freq];
                }
            }

            $controlName = " <a href='".route('compliance-project-control-show', [$project->id, $control->id, 'tasks']) . "'>" . $control->name . '</a>';

            $isApplicable = $control->applicable ? true : false;

            $applicable = [
                'isApplicable' => $isApplicable,
                'applicableValue' => $control->id,
                'isDisabled' => $disabled,
            ];

            $finalData['responsibleUsers']['availableUsers'] = array_values($finalData['responsibleUsers']['availableUsers']);
            $finalData['approverUsers']['availableUsers'] = array_values($finalData['approverUsers']['availableUsers']);

            $render[] = [
                $applicable,
                $control->controlId,
                $controlName,
                $control->description,
                $status,
                $finalData["responsibleUsers"],
                $finalData["approverUsers"],
                $deadline,
                $frequencyData,
                $action
            ];

            $finalData['responsibleUsers']['availableUsers'] = [];
            $finalData['approverUsers']['availableUsers'] = [];
        }

        $controls->setCollection(collect($render));

        $response = [
            'draw' => $draw,
            'recordsTotal' => $count,
            'recordsFiltered' => $count,
            'data' => $controls,
        ];
        echo json_encode($response);
    }

    /*
    |--------------------------------------------------------------------------
    | download control evidences
    |--------------------------------------------------------------------------
    */
    public function downloadEvidences(Request $request, $project, $projectControlId, $id, $linkedToControlId = null)
    {
        if (!$this->loggedUser->hasAnyRole([$this->globalAdmin, 'Compliance Administrator', 'Auditor', 'Contributor'])) {
            return RegularFunctions::accessDeniedResponse();
        }

        $document = Evidence::findorfail($id);

        $pcontrol = $document->projectControlWithoutDataScope;

        if ($this->loggedUser->hasAnyRole(['Contributor'])) {
            $allowed = $pcontrol->responsible == $this->loggedUser->id || $pcontrol->approver == $this->loggedUser->id;

            // when linked control evidences are downloaded
            if ($linkedToControlId) {
                $linkedToProjectControlEvidence = Evidence::where('path', $document->project_control_id)
                    ->where('project_control_id', $linkedToControlId)
                    ->where('type', 'control')
                    ->firstOrFail();
                $linkedToProjectControl = $linkedToProjectControlEvidence->projectControl;

                $linkedEvidenceAllowed = $linkedToProjectControl->responsible == $this->loggedUser->id || $linkedToProjectControl->approver == $this->loggedUser->id;

                if ($linkedEvidenceAllowed != true) {
                    exit;
                }
            } else {
                if ($allowed != true) {
                    exit;
                }
            }
        }
        $encryptedContents = Storage::get($document->path);

        $baseName = basename($document->path);
        $decryptedContents = decrypt($encryptedContents);

        return response()->streamDownload(function () use ($decryptedContents) {
            echo $decryptedContents;
        }, $baseName);
    }

    /**
     * Controls evidences.
     **/
    public function evidences(Request $request, Project $project, ProjectControl $projectControl)
    {
        $start = $request->start;
        $length = $request->length;
        $draw = $request->draw;
        $evidences = Evidence::where('project_control_id', $projectControl->id)->orderBy('id', 'desc')->get();

        $render = [];
        foreach ($evidences as $evidence) {
            $evidenceType = $evidence->type;
            $evidenceName = $evidence->name;

            $urlLink = "<a class='btn btn-secondary btn-xs waves-effect waves-light' title='Download' href='" . route('compliance-project-control-evidences-download', [$project->id, $evidence->project_control_id, $evidence->id]) . "'><i class='fe-download' style='font-size:12px;'></i></a>";
            if ($evidence->type === "text") {
                $urlLink = "<button class='btn btn-secondary btn-xs waves-effect waves-light open-evidence-text-modal' title='Display' data-evidence-id='" . $evidence->id . "'><i class='fe-eye' style='font-size:12px;'></i></button>";
            }

            switch ($evidenceType) {
                case 'control':
                    $evidenceName = 'This control is linked to <a class="link-primary" href=' . route('project-control-linked-controls-evidences-view', [$project->id, $evidence->path, $evidence->project_control_id]) . ">{$evidence->name}
                                </a>
                                ";
                    $urlLink = "<a class='btn btn-secondary btn-xs waves-effect waves-light' title='Link' href='" . route('project-control-linked-controls-evidences-view', [$project->id, $evidence->path, $evidence->project_control_id]) . "'><i class='fe-link' style='font-size:12px;'></i></a>";
                    break;
                case 'link':
                    $urlLink = "<a class='btn btn-secondary btn-xs waves-effect waves-light' title='Link' href='" . $evidence->path . "' target='_blank'><i class='fe-link' style='font-size:12px;'></i></a>";
                    break;
                default:
                    $urlLink = $urlLink.'';
            }

            if ($this->loggedUser->id == $projectControl->responsible) {
                if ($evidence->projectControl->status == $this->notImplemented || $evidence->projectControl->status == 'Rejected') {
                    $deleteLink = "<a class='evidence-delete-link btn btn-danger btn-xs waves-effect waves-light' href='" . route('compliance-project-control-evidences-delete', [$project->id, $projectControl->id, $evidence->id]) . "' title='Delete'><i class='fe-trash-2' style='font-size:12px;'></i></a>";
                } else {
                    $deleteLink = '';
                }
            } else {
                $deleteLink = '';
            }

            $actions = "<div class='btn-group'>" . $urlLink . $deleteLink . '</div>';
            $render[] = [
                $evidenceName,
                date($this->dateFormat, strtotime($evidence->deadline)),
                date($this->dateFormat, strtotime($evidence->created_at)),
                $actions,
            ];
        }

        $response = [
            'draw' => $draw,
            'recordsTotal' => count($evidences),
            'recordsFiltered' => count($evidences),
            'data' => $render,
        ];

        return response()->json($response);
    }

    /*
    * Returns controls show page
    */
    public function show(Request $request, Project $project, ProjectControl $projectControl, $activeTabs = 'details')
    {
        $projectControl->load('template.latest');
        $projectControl->append('automation_meta');
        // $this->project->of_standard->controls->where('name', $this->name)->first();
        // $projectControl->with('project.of_standard.controls');
        $projectControl->append('automation_meta');
        $projectControlId = $projectControl->id;

        if (!AccessControlHelpers::viewProjectControlDetails($this->loggedUser, $projectControl)) {
            return RegularFunctions::accessDeniedResponse();
        }

        //getting standard having project only
        $allStandards = Standard::whereHas('projects')->get();

        $comments = Comment::where('project_control_id', $projectControlId)->with(['sender' => function ($q) {
            $q->select(['id', 'first_name', 'last_name']);
        }])->get();

        $frequencies = RegularFunctions::getFrequency();

        $evidence = Evidence::where('project_control_id', $projectControlId)->first();

        if ($evidence) {
            $latestJustification = Justification::where('project_control_id', $projectControlId)->with(['creator' => function ($q) {
                return $q->select(['id', 'first_name', 'last_name']);
            }])->latest('created_at')->first();
        } else {
            $latestJustification = null;
        }

        $projectControlEvidences = $projectControl->evidences();

        $projectControl['required_values'] = null;

        if($projectControl->automation === 'document'){

            $collection = new \Illuminate\Database\Eloquent\Collection;
            $collection = $collection->merge([$projectControl->evidences]);
            $collection = $collection->merge(Evidence::where(['project_control_id'=>$projectControlId, 'type' => 'additional'])->get());
            $projectControl['merged_evidences'] = $collection;

        }else if($projectControl->automation === 'technical'){

            $collection = new \Illuminate\Database\Eloquent\Collection;
            $collection = $collection->merge($projectControlEvidences->where('type','json')->get());
            $collection = $collection->merge($projectControl->evidences()->where('type','!=','json')->get());
            $projectControl['merged_evidences'] = $collection;
        }else{
            $projectControl['merged_evidences'] = $projectControlEvidences->where('type','!=','json')->get();
        }

        //$allowEvidencesUpload = $projectControl->status == 'Not Implemented' || $projectControl->status == 'Rejected' ? true : false;
        $allowEvidencesUpload = $projectControl->status == $this->notImplemented ||
            $projectControl->status == 'Rejected' ||
            $projectControl->amend_status == "accepted" ||
            $projectControl->amend_status == "requested_approver";
        $nextReviewDate = RegularFunctions::nextReviewDate($projectControl);

        if($nextReviewDate){
            $nextReviewDate = Carbon::createFromFormat('Y-m-d', $nextReviewDate)->format('d-m-Y');
        }

        if (auth('admin')->user()->hasAnyRole($this->globalComplianceAdminRoles)) {
            if (!$projectControl->applicable) {
                $disabled = true;
                $allowUpdate = false;
            } else {
                if (!$projectControl->is_editable) {
                    $disabled = true;
                    $allowUpdate = false;
                } else {
                    $disabled = false;
                    $allowUpdate = true;
                }
            }
        } else {
            $disabled = true;
            $allowUpdate = false;
        }

        if($projectControl->automation === 'document') {
            $nextReviewDate = null;
            $scope = RegularFunctions::convertScopeToString($projectControl->scope);
            $request->request->add(['data_scope' => $scope]);

            if($projectControl->template->versions()->where('status', 'published')->exists() || ($projectControl->status === 'Implemented' && $projectControl->template->is_generated)){
                $disabled = true;
                $allowUpdate = false;
            }
            $request->request->remove('data_scope');
        }
        //to check if campaign has been run for current awareness control
        if($projectControl->automation === 'awareness') {
            $projectControl->is_campaign_run = $projectControl->is_editable;
        }

        // checking if the control is of the same department (and child department) or not and if not, disabling edit control
        $scope = Scopable::where([['scopable_id', $projectControlId], ['scopable_type', 'App\Models\Compliance\ProjectControl']])->first();
        $is_of_same_department = RegularFunctions::compareProjectControlScopeWithUserScope($scope);
        if (!$is_of_same_department) {
            $projectControl->is_editable = false;
            $disabled = true;
            $allowUpdate = false;
        }

        $meta = [
            'disabled' => $disabled,
            'update_allowed' => $allowUpdate,
            'evidence_upload_allowed' => $allowEvidencesUpload,
            'evidence_delete_allowed' => $this->loggedUser->id === $projectControl->responsible && ($projectControl->status == $this->notImplemented || $projectControl->status == 'Rejected')
        ];

        // amend evidence stuff
        $justificationStatuses = [
            'Evidence amendment requested',
            'Evidence amendment request rejected',
            'Rejected'
        ];

        if($projectControl->status === 'rejected'){
            $justificationStatus = $justificationStatuses[2];
        } elseif($projectControl->amend_status === 'rejected') {
            $justificationStatus = $justificationStatuses[1];
        } else {
            $justificationStatus = $justificationStatuses[0];
        }

        $hasLinkedEvidence=false;
        $linkedEvidencesControl = ProjectControl::where('id', $projectControl->id)->has('evidences')->with(['evidences','project'])->whereHas('evidences', function ($q) {
            $q->where('type', 'control');
        })->first();
        if($linkedEvidencesControl){
            $control_evidence = Evidence::where([['project_control_id', $projectControlId],['type','control']])->first();
            $linkedEvidencesControl=ProjectControl::where('id', (int)$control_evidence->path)->with('project')->first();
            $hasLinkedEvidence=true;
        }

        $integration_control = IntegrationControl::where('primary_id', $projectControl->primary_id)->where('sub_id', $projectControl->sub_id)->where('standard_id', $project->standard_id)->first();
        $integrations = $integration_control?->integrations()->where('connected', true)->get();

        //to check if SSO is enable or not
        $microsoftSSO = $this->getConnectedIntegrationWithSlug('office-365');
        $googleSSO = $this->getConnectedIntegrationWithSlug('google-cloud-identity');
        $oktaSSO = $this->getConnectedIntegrationWithSlug('okta');

        $ssoIsEnabled = $microsoftSSO || $googleSSO || $oktaSSO;

        $hasPolicyRole = auth('admin')->user()->hasAnyRole($this->policyAdministratorRoles);
        $hasComplianceRole = auth('admin')->user()->hasAnyRole($this->complianceAdministratorRoles);

        $manualOverrideResponsibleRequired = false;
        if ($projectControl->isSgdControl || $projectControl->automation =="awareness")
        {
            if (isset($projectControl->responsible))
            {
                $manualOverrideResponsibleRequired = !$this->checkIfResponsibleOfSameDepartment($projectControl->responsible,$projectControl->project);
            }
        }

        return Inertia::render('compliance/project-controls/show/Index', compact(
            'project',
            'projectControl',
            'hasLinkedEvidence',
            'linkedEvidencesControl',
            'meta',
            'frequencies',
            'activeTabs',
            'nextReviewDate',
            'latestJustification',
            'allStandards',
            'comments',
            'justificationStatus',
            'integrations',
            'ssoIsEnabled',
            'hasPolicyRole',
            'hasComplianceRole',
            'manualOverrideResponsibleRequired'
        ));
    }

    private function getConnectedIntegrationWithSlug($slug)
    {
        $integration = Integration::where('slug', $slug)->where('connected', 1)->first();

        return $integration ? true : false;
    }

    //checkEvidence field is added to separate function from api call to local function call
    public function getMergedEvidences($project, $projectControl, $checkEvidence = false)
    {
        $evidence = $this->getControlEvidences($projectControl);
        if (!$checkEvidence) {
            return $evidence;
        }
        if ($evidence && $checkEvidence) {
            foreach ($evidence as $value) {
                if (!is_null($value['text_evidence'])) {
                    return 'Implemented';
                }
            }
            return 'Not Implemented';
        }
    }

    public function getCampaignDataId(){
        return response()->json([
            'success' => true,
            'data' => Campaign::where('campaign_type','awareness-campaign')->latest()->first()
        ]);
    }

    public function automate($project, Request $request)
    {
        $request->validate([
            'controls' => 'required|array|min:1',
            'controls.*' => 'exists:compliance_project_controls,id',
            'deadline' => 'required|date',
            'responsible' => 'required|exists:admins,id'
        ]);
        $project = Project::withoutGlobalScope(new DataScope)->findOrFail($project);

        $data = [];
        foreach ($request->controls as $control) {
            $data[] = [
                'id' => $control,
            ];
        }
        $this->updateProjectControlAutomation($project, $data, $request);

        $project->controls()->whereIn('id', $request->controls)->each(function ($control) use ($request) {
           $control->update(['responsible' => $request->responsible, 'deadline' => $request->deadline]);
        });

        return redirect()->back()->withSuccess('Controls automated successfully.');
    }

    public function overrideToManual($project, Request $request){
        $request->validate([
            'controls' => 'required|array|min:1',
            'controls.*' => 'exists:compliance_project_controls,id',
            'deadline' => 'required|date',
            'responsible' => 'required|exists:admins,id',
            'approver' => 'required|exists:admins,id'
        ]);
        $data = [];
        foreach ($request->controls as $control) {
            $data[] = [
                'id' => $control,
            ];
        }
        $project = Project::withoutGlobalScope(new DataScope)->findOrFail($project);
        $this->updateProjectControlAutomation($project, $data, $request);

        $project->controls()->whereIn('id', $request->controls)->each(function ($control) use ($request) {
            $control->update(['responsible' => $request->responsible, 'deadline' => $request->deadline, 'approver' => $request->approver, 'frequency' => $request->frequency]);
        });

        return redirect()->back()->withSuccess('Controls were set as manual successfully.');
    }

    /*
    |--------------------------------------------------------------------------
    | stores comment
    |--------------------------------------------------------------------------
    */
    public function storeComment(Request $request, Project $project, ProjectControl $projectControl)
    {
        $request->validate([
            'comment' => 'required',
        ]);

        $input = $request->toArray();

        // access control >> allowed to comment by responsible and approvar
        if (!($projectControl->responsible == $this->loggedUser->id || $projectControl->approver == $this->loggedUser->id)) {
            return RegularFunctions::accessDeniedResponse();
        }

        $comment = new Comment();

        $comment->project_control_id = $projectControl->id;
        $comment->comment = $input['comment'];

        if ($projectControl->responsible == $this->loggedUser->id) {
            $comment->from = $this->loggedUser->id;
            $comment->to = $projectControl->approver;
            $comment_by = "responsible";
        } elseif ($projectControl->approver == $this->loggedUser->id) {
            $comment->from = $this->loggedUser->id;
            $comment->to = $projectControl->responsible;
            $comment_by = "approver";
        }

        $comment_saved = $comment->save();


        if ($comment_saved) {
            $admins = Admin::whereIn('id', [$comment->from, $comment->to])->get();
            $comment_from = $admins->where('id', $comment->from)->first();
            $comment_to = $admins->where('id', $comment->to)->first();

            $subject = "New comment - ". $project->name;

            $email_data = [
                'greeting' => $this->emailContentHello . ucwords(decodeHTMLEntity($comment_to->full_name)),
                'content1' => "A new comment has been added to a control that you are ". $comment_by ." for.",
                'content2' => $this->emailContentProjectName . decodeHTMLEntity($project->name),
                'content3' => $this->emailContentStandard . decodeHTMLEntity($project->standard),
                'content4' => $this->emailContentControlID . decodeHTMLEntity($projectControl->controlId),
                'content5' => $this->emailContentControlName . decodeHTMLEntity($projectControl->name),
                'content6' => "<b style='color: #000000'>Comment: </b>". $comment->comment,
                'content7' => "",
                'content8' => "<b style='color: #000000'>Commenter: </b>". ucwords(decodeHTMLEntity($comment_from->full_name)),
                'action' => [
                    'action_title' => 'Click the below button to go to the task.',
                    'action_url' => route('compliance-project-control-show', [$project->id, $projectControl->id, 'tasks']),
                    'action_button_text' => "View control",
                ]
            ];

            Notification::route('mail', $comment_to->email)->notify(new AssignedTaskNotification($email_data, $subject));
        }

        return redirect()->back();
    }

    /*
    |--------------------------------------------------------------------------
    | Submitting controls for review
    |--------------------------------------------------------------------------
    */
    public function submitForReview(Request $request, Project $project, ProjectControl $projectControl)
    {
        $evidences = $projectControl->evidences;

        /* Only responsible users can submit*/
        if ($projectControl->responsible != $this->loggedUser->id) {
            return response()->json([
                'message' => 'Access Denied!',
            ]);
        }

        if (is_null($evidences) && count($evidences) == 0) {
            return response()->json([
                'message' => 'evidence not found!',
            ]);
        } else {
            if ($projectControl->status == 'Rejected') {
                $evidenceDocsUploadedAfterRejectionCount = $evidences->where('updated_at', '>', $projectControl->rejected_at)->count();

                if ($evidenceDocsUploadedAfterRejectionCount == 0) {
                    return redirect()->back()->withError('Access Denied');
                }
            }
        }

        $approver = Admin::findorfail($projectControl->approver);
        $responsible = Admin::findorfail($projectControl->responsible);

        if ($approver && $responsible) {

            $subject = 'Pending approval';

            $data = [
                'greeting' => $this->emailContentHello . ucwords(decodeHTMLEntity($approver->full_name)),
                'content1' => 'Evidence has been submitted, and your approval is required for the following task:',
                'content2' => $this->emailContentProjectName . decodeHTMLEntity($project->name),
                'content3' => $this->emailContentStandard . decodeHTMLEntity($project->standard),
                'content4' => $this->emailContentControlID . decodeHTMLEntity($projectControl->controlId),
                'content5' => $this->emailContentControlName . decodeHTMLEntity($projectControl->name),
                'content6' => '',
                'content7' => '',
                'email' => $approver->email,
                'action' => [
                    'action_title' => 'Click the below button to view the submitted evidence and to carry out the approval.',
                    'action_url' => route('compliance-project-control-show', [$project->id, $projectControl->id, 'tasks']),
                    'action_button_text' => $this->gotoTaskDetails,
                ],
            ];

            try {
                DB::beginTransaction();
                $initialAmendStatus = $projectControl->amend_status;

                $projectControl->amend_status = "submitted";
                $projectControl->status = $this->underReview;
                $projectControl->is_editable = 0;
                $projectControl->save();

                /* Changing the evidence(s) status */
                if(in_array($initialAmendStatus, ['requested_approver', 'requested_responsible', 'accepted']))
                {
                    Evidence::where('project_control_id', $projectControl->id)
                        ->where('status', 'initial')
                        ->update([
                            'status' => 'review'
                        ]);
                } else {
                    Evidence::where('project_control_id', $projectControl->id)->update([
                        'status' => 'review'
                    ]);
                }

                // when done commit
                DB::commit();
            } catch (\Throwable $th) {
                DB::rollBack();


                return response()->json([
                    'status' => false,
                    'message' => $this->somethingWrong,
                ]);
            }

            // updating `Submit To Approver Allowed Status`
            RegularFunctions::updateTaskEvidencesUploadAllowedStatus($projectControl->id, 0);

            /* Finding whether the current control has any linked evidence(s) */
            $linkedEvidenceStatus = Evidence::where([['project_control_id', $projectControl->id],['type','control']])->get();

            if($linkedEvidenceStatus->count() > 0){
                return $this->controlReviewApprove($request,$project,$projectControl,true);
            }

            /* Sending mail to assigned (approver) */
            Notification::route('mail', $data['email'])
                ->notify(new AssignedTaskNotification($data, $subject));

        }

        return redirect()->back();
    }

    /*
    |--------------------------------------------------------------------------
    | update controls
    |--------------------------------------------------------------------------
    */
    public function update(Request $request, Project $project, $projectControlId)
    {
        $scope = Scopable::where([['scopable_id', $projectControlId], ['scopable_type', 'App\Models\Compliance\ProjectControl']])->first();
        $isOfSameDepartment = RegularFunctions::compareProjectControlScopeWithUserScope($scope);
        if (!Auth::guard('admin')->user()->hasAnyRole($this->globalComplianceAdminRoles) && !$isOfSameDepartment) {
            return RegularFunctions::accessDeniedResponse();
        }
        $projectControl = ProjectControl::where('id', $projectControlId)->first();

        $request->validate([
            'responsible' => 'required',
            'approver' => $projectControl->automation === 'none' ? 'required|different:responsible' : '',
            'deadline' => $projectControl->automation !== 'technical' ? 'required|after_or_equal:today' : '',
            'frequency' => $projectControl->automation === 'none' ? 'required|in:One-Time,Monthly,Every 3 Months,Bi-Annually,Annually' : '',
        ]);

        $input = $request->only('responsible', 'deadline', 'frequency');

        if($projectControl->automation === 'none'){
            $input['approver'] = $request->approver;
        } elseif($projectControl->automation === 'document') {
            $input['frequency'] = 'Annually';
        }

        $currentDate = (new \DateTime())->format('Y-m-d');
        $isValidDeadline = $input['deadline'] >= $currentDate;

        // Not allowing the Deadline to be less than today
        if (!$isValidDeadline) {
            unset($input['deadline']); // remove item deadline
        }

        //Not allowing the responsible and approver to be same
        if (array_key_exists('approver', $input) && $input['responsible'] == $input['approver']) {
            unset($input['responsible']); // remove item index responsible
            unset($input['approver']); // remove item index approver
        }

        // Update not allowed for non applicable
        $isNotApplicable = ProjectControl::where('id', $projectControlId)->where('applicable', 0)->first();

        if ($isNotApplicable) {
            return redirect()->back();
        }

        // Update not allowed for non editable
        $isNotEditable = ProjectControl::where('id', $projectControlId)->where('is_editable', 0)->first();

        if ($isNotEditable) {
            return redirect()->back();
        }

        // project control before update
        $beforeUpdateProjectControl = $projectControl->toArray();

        // updating project Control
        $projectControl->update($input);

        // allow task reminder check if the deadline changed
        if(array_key_exists('deadline', $input) && !is_null($input['deadline']) && $input['deadline'] != $beforeUpdateProjectControl['deadline']){
            // remove the schedule record of today
            ComplianceProjectTaskScheduleRecord::query()
                ->whereDate('created_at', today())
                ->where('name', 'taskDeadlineReminder')
                ->where('compliance_project_control_id', $projectControl->id)
                ->delete();
        }

        // Sending email to responsible when responsible user changed
        if (array_key_exists('responsible', $input) && !is_null($input['responsible']) && $input['responsible'] != $beforeUpdateProjectControl['responsible']) {

            $user = Admin::find($projectControl->responsible);

            $subject = $this->newTaskAssignment;

            $data = [
                'greeting' => $this->emailContentHello . ucwords(decodeHTMLEntity($user->full_name)),
                'content1' => 'You have been assigned responsibility for a new task. Please find the details below:',
                'content2' => $this->emailContentProjectName . decodeHTMLEntity($project->name),
                'content3' => $this->emailContentStandard . decodeHTMLEntity($project->standard),
                'content4' => $this->emailContentControlID . decodeHTMLEntity($projectControl->controlId),
                'content5' => $this->emailContentControlName . decodeHTMLEntity($projectControl->name),
                'content6' => '<b style="color: #000000;">Deadline: </b> ' . date($this->dateFormat, strtotime($projectControl->deadline)),
                'content7' => '',
                'action' => [
                    'action_title' => '',
                    'action_url' => route('compliance-dashboard'),
                    'action_button_text' => $this->goToDashboard,
                ],
            ];

            Notification::route('mail', $user->email)
                ->notify(new AssignedTaskNotification($data, $subject));

            $admin = Admin::find($beforeUpdateProjectControl['responsible']);

            $subjects = $this->removalTaskAssignment;

            if ($admin) {
                $removaldata = [
                    'greeting' => $this->emailContentHello . ucwords(decodeHTMLEntity($admin->full_name)),
                    'content1' => $this->removalTaskAssignmentMessage,
                    'content2' => $this->emailContentProjectName . decodeHTMLEntity($project->name),
                    'content3' => $this->emailContentStandard . decodeHTMLEntity($project->standard),
                    'content4' => $this->emailContentControlID . decodeHTMLEntity($projectControl->controlId),
                    'content5' => $this->emailContentControlName . decodeHTMLEntity($projectControl->name),
                    'content6' => '',
                    'content7' => $this->removalTaskAssignmentAction,
                ];

                Notification::route('mail', $admin->email)
                    ->notify(new RemoveTaskNotification($removaldata, $subjects));
            }
        }

        // Sending email to approver when approver user changed
        if (array_key_exists('approver', $input) && !is_null($input['approver']) && $input['approver'] != $beforeUpdateProjectControl['approver']) {
            $user = Admin::find($projectControl->approver);

            $subject = $this->mailSubject;
            $data = [
                'greeting' => $this->emailContentHello . ucwords(decodeHTMLEntity($user->full_name)),
                'content1' => $this->mailTitleContent,
                'content2' => $this->emailContentProjectName . decodeHTMLEntity($project->name),
                'content3' => $this->emailContentStandard . decodeHTMLEntity($project->standard),
                'content4' => $this->emailContentControlID . decodeHTMLEntity($projectControl->controlId),
                'content5' => $this->emailContentControlName . decodeHTMLEntity($projectControl->name),
                'content6' => '<b style="color: #000000;">Deadline: </b> ' . date($this->dateFormat, strtotime($projectControl->deadline)),
                'content7' => $this->mailInformationContent,
            ];

            Notification::route('mail', $user->email)
                ->notify(new AssignedTaskNotification($data, $subject));

            $admin = Admin::find($beforeUpdateProjectControl['approver']);

            $subjects = $this->removalApprovalResponsibility;

            if ($admin) {
                $removaldata = [
                    'greeting' => $this->emailContentHello . ucwords(decodeHTMLEntity($admin->full_name)),
                    'content1' => 'You have been removed as an approver from the following tasks:',
                    'content2' => $this->emailContentProjectName . decodeHTMLEntity($project->name),
                    'content3' => $this->emailContentStandard . decodeHTMLEntity($project->standard),
                    'content4' => $this->emailContentControlID . decodeHTMLEntity($projectControl->controlId),
                    'content5' => $this->emailContentControlName . decodeHTMLEntity($projectControl->name),
                    'content6' => '',
                    'content7' => $this->removalTaskAssignmentAction,
                ];

                Notification::route('mail', $admin->email)
                    ->notify(new RemoveTaskNotification($removaldata, $subjects));
            }
        }

        return redirect()->back()->withSuccess('Control Detail is successfully updated');
    }

    /*
    |--------------------------------------------------------------------------
    | Sending Task first time assignment mail
    |--------------------------------------------------------------------------
    */
    public function sendControlsAssignmentMail($projectControls, $project)
    {
        $uniqueResponsibleUsers = $projectControls->unique('responsible');
        $uniqueApproverUsers = $projectControls->unique('approver');

        /* Sending responsible users mail notification of un-assigment */
        if ($uniqueResponsibleUsers) {
            foreach ($uniqueResponsibleUsers as $uniqueResponsibleProjectControl) {
                /* Filtering out controls to assign  */
                $cols = $projectControls->where('responsible', $uniqueResponsibleProjectControl->responsible);

                $controls = $cols->filter(function ($item) {
                    return $item->sent_to_responsible == true;
                });

                if (count($controls) > 0) {
                    $subject = $this->newTaskAssignment;
                    $data = [
                        'greeting' => $this->emailContentHello . decodeHTMLEntity($uniqueResponsibleProjectControl->responsibleUser->full_name),
                        'title' => 'You have been assigned responsibility for a new task. Please find the details below:',
                        'project' => $project,
                        'projectControls' => $controls,
                        'information' => '',
                        'action' => [
                            'action_title' => '',
                            'action_url' => route('compliance-dashboard'),
                            'action_button_text' => $this->goToDashboard,
                        ],
                    ];
                    ProjectControlJob::dispatch($uniqueResponsibleProjectControl->responsibleUser->email,$data,$subject);
                }
            }
        }


        /* Sending approver users mail notification of un-assigment */
        if ($uniqueApproverUsers) {
            foreach ($uniqueApproverUsers as $uniqueApproverProjectControl) {

                /* Filtering out controls to be un-assigned */
                $cols = $projectControls->where('approver', $uniqueApproverProjectControl->approver);

                $controls = $cols->filter(function ($item) {
                    return $item->sent_to_approver == true;
                });

                if (count($controls) > 0) {
                    $subject = $this->mailSubject;
                    $data = [
                        'greeting' => $this->emailContentHello . decodeHTMLEntity($uniqueApproverProjectControl->approverUser->full_name),
                        'title' => $this->mailTitleContent,
                        'project' => $project,
                        'projectControls' => $controls,
                        'information' => $this->mailInformationContent,
                    ];
                    ProjectControlJob::dispatch($uniqueApproverProjectControl->approverUser->email,$data,$subject);
                }
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | uploading control evidences
    |--------------------------------------------------------------------------
    */
    public function uploadEvidences(Request $request, Project $project, ProjectControl $projectControl)
    {
        switch ($request->input('active_tab')) {
            case "upload-docs":
                $this->validate($request, [
                    'project_control_id' => 'required',
                    'name2' => 'required|string|max:191',
                    'evidences' => ['required', 'max:15300', new AllowedEvidence()],
                ], [
                    'name2.required' => 'The name field is required',
                    'evidences.max' => 'The upload max filesize is 15MB. Please upload file less than 15MB.'
                ]);
                break;
            case 'create-link':
                $this->validate($request, [
                    'project_control_id' => 'required',
                    'name' => 'required|string|max:191',
                    'link' => [
                        'required',
                        'string',
                        'max:191',
                        new ValidateUrlOrNetworkFolder
                    ]
                ]);
                break;
            case 'existing-control':
                $this->validate($request, [
                    'project_control_id' => 'required',
                    'linked_to_project_control_id' => 'required'
                ], [
                    'linked_to_project_control_id.required' => 'Please select a control first.'
                ]);
                break;
            case 'text-input':
                $this->validate($request, [
                    'project_control_id' => 'required',
                    'text_evidence' => 'required|string',
                    'text_evidence_name' => 'required|string'
                ], [
                    'text_evidence.required' => 'Text field is required',
                    'text_evidence_name.required' => 'Name field is required'
                ]);
        }

        $input = $request->toArray();

        if ($projectControl) {
            $projectControlId = $projectControl->id;

            if ($projectControl->responsible != $this->loggedUser->id) {
                exit;
            }

            // evidences as documents
            if ($request->hasFile('evidences') && $request->name2) {
                $document = $request->file('evidences');
                $fileName = strval($document->getClientOriginalName());
                $uploadedDocument = Evidence::create([
                    'project_control_id' => $projectControlId,
                    'name' => $input['name'] ?: $input['name2'],
                    'path' => $fileName,
                    'type' => 'document',
                    'deadline' => $projectControl->deadline,
                    'status' => 'initial'
                ]);

                $filePath = "private/compliance/evidences/{$uploadedDocument->id}/{$fileName}";
                // Get File Content
                $documentContent = $document->get();
                // Encrypt the Content
                $encryptedContent = encrypt($documentContent);
                // Store the encrypted Content
                Storage::put($filePath, $encryptedContent, 'private');

                $uploadedDocument->update([
                    'path' => $filePath,
                ]);
            }

            // Evidences as link
            if ($request->link && $request->name) {
                Evidence::create([
                    'project_control_id' => $projectControlId,
                    'name' => $input['name'] ?: $input['name2'],
                    'path' => $input['link'],
                    'type' => 'link',
                    'deadline' => $projectControl->deadline,
                    'status' => 'initial'
                ]);
            }

            // evidences as existing controls
            if (!is_null($request->linked_to_project_control_id)) {
                $projectControl = ProjectControl::with('evidences')->firstWhere('id', $request->project_control_id);
                $linkedToProjectControl = ProjectControl::find($request->linked_to_project_control_id);

                if($linkedToProjectControl){
                    if ($projectControl->evidences->first() && $projectControl->evidences->first()->type == 'control'){ // Checking if linked control exists
                        $projectControl->evidences->first()->update([
                            'name' => $linkedToProjectControl->name,
                            'path' => $linkedToProjectControl->id
                        ]);
                    } else {
                        //Creating evidence
                        Evidence::create([
                            'project_control_id' => $projectControlId,
                            'name' => $linkedToProjectControl->name,
                            'path' => $linkedToProjectControl->id,
                            'type' => 'control',
                            'deadline' => $projectControl->deadline,
                            'status' => 'initial'
                        ]);
                    }

                    //Mirroring this projectControl with the linked projectControl
                    $projectControl->update([
                        'status' => $linkedToProjectControl->status,
                        'is_editable'=>$linkedToProjectControl->automation === 'technical' ? 0 : $linkedToProjectControl->is_editable,
                        'frequency' => $linkedToProjectControl->frequency,
                        'deadline' => $linkedToProjectControl->deadline,
                        'amend_status'=>null
                    ]);

                }

            }

            // evidences as text
            if ($request->text_evidence) {
                $evidence = Evidence::create([
                    'project_control_id' => $projectControlId,
                    'name' => $input['text_evidence_name'],
                    'text_evidence' => $input['text_evidence'],
                    'path' => "text evidence",
                    'type' => 'text',
                    'deadline' => $projectControl->deadline,
                    'status' => 'initial'
                ]);
            }

            /* Rejected evidences are deleted when new evidece(s) are uploaded */
            if ($projectControl->isEligibleForReview) {
                $rejectedEvidences = Evidence::where('project_control_id', $projectControl->id)->where('status', 'rejected')->get();

                foreach ($rejectedEvidences as $key => $rejectedEvidence) {
                    if ($rejectedEvidence->type == 'document') {
                        $exists = Storage::exists($rejectedEvidence->path);

                        if ($exists) {
                            Storage::deleteDirectory(dirname($rejectedEvidence->path));
                        }
                    }

                    /* Deleting the evidence(s) record from DB*/
                    $rejectedEvidence->delete();
                }
            }
        }

        return redirect()->back()->withSuccess('Evidence successfully uploaded');
    }

    public function uploadAdditionalEvidences(Request $request, Project $project, ProjectControl $projectControl)
    {
        $this->validate($request, [
            'project_control_id' => 'required',
            'name2' => 'required|string|max:191',
            'evidences' => ['required', 'max:10240', new AllowedEvidence()],
        ], [
            'name2.required' => 'The name field is required',
            'evidences.max' => 'The upload max filesize is 10MB. Please upload file less than 10MB.'
        ]);

        $input = $request->toArray();

        if ($projectControl) {
            $projectControlId = $projectControl->id;

            if ($projectControl->responsible != $this->loggedUser->id) {
                return redirect()->back()->withErrors('User is not allowed to add additional evidence.');
            }

            if ($request->hasFile('evidences') && $request->name2) {
                $document = $request->file('evidences');
                $fileName = strval($document->getClientOriginalName());
                $uploadedDocument = Evidence::create([
                    'project_control_id' => $projectControlId,
                    'name' => $input['name'] ?: $input['name2'],
                    'path' => $fileName,
                    'type' => 'additional',
                    'deadline' => $projectControl->deadline,
                    'status' => 'initial'
                ]);

                $filePath = "private/compliance/evidences/{$uploadedDocument->id}/{$fileName}";
                // Get File Content
                $documentContent = $document->get();
                // Encrypt the Content
                $encryptedContent = encrypt($documentContent);
                // Store the encrypted Content
                Storage::put($filePath, $encryptedContent, 'private');

                $uploadedDocument->update([
                    'path' => $filePath,
                ]);
                $evidence = $uploadedDocument;
            }

        }

        return redirect()->back()->withSuccess('Additional evidence successfully uploaded!');
    }

    /*
    |--------------------------------------------------------------------------
    | delete evidences
    |--------------------------------------------------------------------------
    */
    public function deleteEvidences(Request $request, $project, $projectControl, $id)
    {
        $evidence = Evidence::withoutGlobalScope(DataScope::class)->findorfail($id);
        $projectControlData = ProjectControl::withoutGlobalScope(DataScope::class)->findorfail($projectControl);
        if ($projectControlData->responsible != $this->loggedUser->id) {
            return RegularFunctions::accessDeniedResponse();
        } else {
            if ($projectControlData->status == 'Approved' || $projectControlData->status == $this->underReview) {
                return RegularFunctions::accessDeniedResponse();
            }
        }

        if ($evidence->type == 'document') {
            $exists = Storage::exists($evidence->path);

            if ($exists) {
                Storage::deleteDirectory(dirname($evidence->path));
            }
        }

        // Deleting unlinked evidence from database
        $evidence->delete();

        return response()->json(['success' => true, 'message' => 'Evidence deleted successfully!']);
    }

    /*
    |--------------------------------------------------------------------------
    | approving controls
    |--------------------------------------------------------------------------
    */
    public function controlReviewApprove(Request $request, Project $project, ProjectControl $projectControl,$directImplement=false)
    {
        if (!$projectControl) {
            exit;
        }

        if ($projectControl->approver != $this->loggedUser->id && !$directImplement) {
            exit;
        }

        $subject = 'Approval of submitted evidence';

        $responsible = Admin::findorfail($projectControl->responsible);

        try {
            DB::beginTransaction();
            if ($projectControl->amend_status == "accepted" || $projectControl->amend_status == "submitted") {
                $projectControl->amend_status = "solved";
            }
            $projectControl->status = 'Implemented';
            $projectControl->current_cycle = $projectControl->current_cycle + 1;
            $projectControl->approved_at = date('Y-m-d H:i:s');
            $projectControl->unlocked_at = null;
            $projectControl->update();

            /* Changing the evidence(s) status */
            Evidence::where('project_control_id', $projectControl->id)->update([
                'status' => 'approved'
            ]);
            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $this->somethingWrong,
            ]);
        }


        $data = [
            'greeting' => $this->emailContentHello . ucwords(decodeHTMLEntity($responsible->full_name)),
            'content1' => 'The evidence you have uploaded for an assigned task has been approved. Please find the details below:',
            'content2' => $this->emailContentProjectName . decodeHTMLEntity($project->name),
            'content3' => $this->emailContentStandard . decodeHTMLEntity($project->standard),
            'content4' => $this->emailContentControlID . decodeHTMLEntity($projectControl->controlId),
            'content5' => $this->emailContentControlName . decodeHTMLEntity($projectControl->name),
            'content6' => '<b style="color: #000000;">Status: </b> Approved',
            'content7' => 'No further action is needed.',
        ];

        Notification::route('mail', $responsible->email)
            ->notify(new AssignedTaskNotification($data, $subject));

        return redirect()->back();
    }

    /*
    |--------------------------------------------------------------------------
    | reject controls
    |--------------------------------------------------------------------------
    */
    public function controlReviewReject(Request $request, Project $project, ProjectControl $projectControl)
    {
        $request->validate([
            'justification' => 'required',
        ]);

        if ($projectControl->approver != $this->loggedUser->id) {
            return redirect()->back()->withError("You aren't an approver");
        }

        $responsible = Admin::findorfail($projectControl->responsible);

        try {
            DB::beginTransaction();
            if (in_array($projectControl->amend_status, ['accepted', 'submitted'])) {
                $projectControl->amend_status = "rejected";
            }

            $projectControl->status = 'Rejected';
            $projectControl->is_editable = 1;
            $projectControl->rejected_at = date('Y-m-d H:i:s');
            $projectControl->save();

            /* Changing the evidence(s) status */
            Evidence::where('project_control_id', $projectControl->id)->where('status', 'review')->update([
                'status' => 'rejected'
            ]);

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $this->somethingWrong,
            ]);
        }


        // update evidence upload
        RegularFunctions::updateTaskEvidencesUploadAllowedStatus($projectControl->id, 1);

        // creating for evidences rejection
        $justification = Justification::create([
            'project_control_id' => $projectControl->id,
            'justification' => $request->justification,
            'for' => 'rejected',
            'creator_id' => $this->loggedUser->id,
        ]);

        $subject = 'Rejection of submitted evidence';

        $data = [
            'greeting' => $this->emailContentHello . ucwords(decodeHTMLEntity($responsible->full_name)),
            'content1' => 'The evidence you have uploaded for an assigned task has been rejected. Please find the details below:',
            'content2' => $this->emailContentProjectName . decodeHTMLEntity($project->name),
            'content3' => $this->emailContentStandard . decodeHTMLEntity($project->standard),
            'content4' => $this->emailContentControlID . decodeHTMLEntity($projectControl->controlId),
            'content5' => $this->emailContentControlName . decodeHTMLEntity($projectControl->name),
            'content6' => '<b style="color: #000000;">Rejection Reason: </b> ' . decodeHTMLEntity($justification->justification),
            'content7' => '',
            'email' => $responsible->email,
            'action' => [
                'action_title' => 'Click the below button to re-upload new evidence.',
                'action_url' => route('compliance-project-control-show', [$project->id, $projectControl->id, 'tasks']),
                'action_button_text' => $this->gotoTaskDetails,
            ],
        ];

        Notification::route('mail', $responsible->email)
            ->notify(new AssignedTaskNotification($data, $subject));

        return redirect()->back();
    }

    public function requestEvidenceAmendment(Request $request, $project, $projectControl)
    {
        $request->validate([
            'justification' => 'required',
        ]);

        $project = Project::withoutGlobalScope(DataScope::class)->find($project);
        $projectControl = ProjectControl::withoutGlobalScope(DataScope::class)->find($projectControl);

        $projectControl->amend_status = $request->requested_by === "responsible" ? "requested_responsible" : "requested_approver";

        if($request->requested_by === "approver") {
            $projectControl->status = $this->notImplemented;
            $projectControl->is_editable = true;
            Evidence::where([
                'project_control_id' => $projectControl->id,
                'type' => 'control'
            ])->delete();
        }
        $projectControl->save();

        /* Changing the evidence(s) status */
        Evidence::where('project_control_id', $projectControl->id)->update([
            'status' => 'rejected'
        ]);

        $justification = Justification::create([
            'project_control_id' => $projectControl->id,
            'justification' => $request->justification,
            'for' => 'amend',
            'creator_id' => $this->loggedUser->id,
        ]);

        $subject = "Request for amendment of evidence";


        if($request->requested_by === "responsible"){
            $user_to_be_notified = $projectControl->approverUser;

            $email_data = [
                'greeting' => $this->emailContentHello . ucwords(decodeHTMLEntity($user_to_be_notified->full_name)),
                'content1' => "The responsible person for the below task has requested to amend the previously provided evidence. Please find the details below: ",
                'content2' => $this->emailContentProjectName . decodeHTMLEntity($project->name),
                'content3' => $this->emailContentStandard . decodeHTMLEntity($project->standard),
                'content4' => $this->emailContentControlID . decodeHTMLEntity($projectControl->controlId),
                'content5' => $this->emailContentControlName . decodeHTMLEntity($projectControl->name),
                'content6' => '<b style="color: #000000;">Amendment Reason: </b> ' . decodeHTMLEntity($request->justification),
                'content7' => "",
                'action' => [
                    'action_title' => 'Click the below button to view the request and to carry out the approval.',
                    'action_url' => route('compliance-project-control-show', [$project->id, $projectControl->id, 'tasks']),
                    'action_button_text' => $this->gotoTaskDetails,
                ]
            ];
        } else {
            $user_to_be_notified = $projectControl->responsibleUser;
            $email_data = [
                'greeting' => $this->emailContentHello . ucwords(decodeHTMLEntity($user_to_be_notified->full_name)),
                'content1' => "You have been requested to amend the evidence you have uploaded for an assigned task. Please find the details below:",
                'content2' => $this->emailContentProjectName . decodeHTMLEntity($project->name),
                'content3' => $this->emailContentStandard . decodeHTMLEntity($project->standard),
                'content4' => $this->emailContentControlID . decodeHTMLEntity($projectControl->controlId),
                'content5' => $this->emailContentControlName . decodeHTMLEntity($projectControl->name),
                'content6' => '<b style="color: #000000;">Amendment Reason: </b> ' . decodeHTMLEntity($request->justification),
                'content7' => "",
                'action' => [
                    'action_title' => 'Click the below button to re-upload new evidence.',
                    'action_url' => route('compliance-project-control-show', [$project->id, $projectControl->id, 'tasks']),
                    'action_button_text' => $this->gotoTaskDetails,
                ]
            ];
        }

        Notification::route('mail', $user_to_be_notified->email)->notify(new AssignedTaskNotification($email_data, $subject));

        Log::info('Evidence amendment was requested');
        return redirect()->back();
    }

    public function amendRequestDecision(Request $request, $project, $projectControl)
    {

        $project = Project::withoutGlobalScope(DataScope::class)->find($project);
        $projectControl = ProjectControl::withoutGlobalScope(DataScope::class)->find($projectControl);

        if ($projectControl->approver != $this->loggedUser->id) {
            return response()->json([
                'status' => 'access denied',
                'message' => 'your are not approver',
                'justification' => 'sometimes|string'
            ]);
        }

        $user_to_be_notified = $projectControl->responsibleUser;
        $request_justification = Justification::where('project_control_id', $projectControl->id)->where('for', 'amend')->latest('created_at')->first();


        if($request->solution === "accepted") {

            try {
                DB::beginTransaction();

                $projectControl->amend_status = $request->solution;
                $projectControl->is_editable = $request->solution === "accepted" ? 1 : 0;
                $projectControl->status = $this->notImplemented;
                $saved = $projectControl->save();
                Evidence::where([
                    'project_control_id' => $projectControl->id,
                    'type' => 'control'
                ])->delete();

                DB::commit();
            } catch (\Throwable $th) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => $this->somethingWrong,
                ]);
            }

            RegularFunctions::updateTaskEvidencesUploadAllowedStatus($projectControl->id, 1);

            $subject = "Approval of amending evidence";

            $email_data = [
                'greeting' => $this->emailContentHello . ucwords(decodeHTMLEntity($user_to_be_notified->full_name)),
                'content1' => "Your request for amending evidence has been approved. Please find the details below:",
                'content2' => $this->emailContentProjectName . decodeHTMLEntity($project->name),
                'content3' => $this->emailContentStandard . decodeHTMLEntity($project->standard),
                'content4' => $this->emailContentControlID . decodeHTMLEntity($projectControl->controlId),
                'content5' => $this->emailContentControlName . decodeHTMLEntity($projectControl->name),
                'content6' => '<b style="color: #000000;">Amendment Reason: </b> ' . decodeHTMLEntity($request_justification->justification),
                'content7' => "",
                'action' => [
                    'action_title' => 'Click the below button to upload amended evidence.',
                    'action_url' => route('compliance-project-control-show', [$project->id, $projectControl->id, 'tasks']),
                    'action_button_text' => $this->gotoTaskDetails,
                ]
            ];
        } else {
            if($request->justification){
                $reject_justification = Justification::create([
                    'project_control_id' => $projectControl->id,
                    'justification' => $request->justification,
                    'for' => 'amend_reject',
                    'creator_id' => $this->loggedUser->id,
                ]);
            }

            try {
                DB::beginTransaction();

                $projectControl->amend_status = "solved";

                $saved = $projectControl->save();

                DB::commit();
            } catch (\Throwable $th) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => $this->somethingWrong,
                ]);
            }

            $subject = "Rejection of amending evidence";

            $email_data = [
                'greeting' => $this->emailContentHello . ucwords(decodeHTMLEntity($user_to_be_notified->full_name)),
                'content1' => "Your request for amending evidence has been rejected. Please find the details below:",
                'content2' => $this->emailContentProjectName . decodeHTMLEntity($project->name),
                'content3' => $this->emailContentStandard . decodeHTMLEntity($project->standard),
                'content4' => $this->emailContentControlID . decodeHTMLEntity($projectControl->controlId),
                'content5' => $this->emailContentControlName . decodeHTMLEntity($projectControl->name),
                'content6' => '<b style="color: #000000;">Amendment Reason: </b> ' . decodeHTMLEntity($request_justification->justification),
                'content7' => "",
                'content8' => '<b style="color: #000000;">Rejection Reason: </b> ' . decodeHTMLEntity($reject_justification ? $reject_justification->justification  : ""),
                'action' => [
                    'action_title' => 'Click the below button to go to the task.',
                    'action_url' => route('compliance-project-control-show', [$project->id, $projectControl->id, 'tasks']),
                    'action_button_text' => $this->gotoTaskDetails,
                ]
            ];
        }

        Notification::route('mail', $user_to_be_notified->email)->notify(new AssignedTaskNotification($email_data, $subject));

        Log::info("Evidence amendment requested was $request->solution");

        return redirect()->back();
    }

    /*
    |--------------------------------------------------------------------------
    | Link control evidence show
    |--------------------------------------------------------------------------
    */
    public function linkedControlEvidencesView(Request $request, Project $project, $projectControlId, $linkedToControlId)
    {
        $evidences = Evidence::where('project_control_id', $projectControlId)->get();

        return inertia('controls/LinkedControlEvidences', compact(
            'project',
            'projectControlId',
            'linkedToControlId',
            'evidences'
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | Link control evidence
    |--------------------------------------------------------------------------
    */
    public function linkedControlEvidences(Request $request, Project $project, $projectControlId, $linkedToControlId)
    {
        $page = $request->page ?? 1;
        $size = $request->per_page ?? 10;
        $render = [];

        $projectControl = projectControl::find($projectControlId);
        if ($projectControl->automation === "technical") {
            $evidences = Evidence::where('project_control_id', $projectControl->id)->where('type', 'json')->skip(--$page * $size)->take($size)->paginate($size);
        } else {
            $evidences = Evidence::where('project_control_id', $projectControl->id)->where('type', '!=', 'json')->skip(--$page * $size)->take($size)->paginate($size);
        }

        foreach ($evidences as $evidence) {
            $evidence['created_date'] = date('d M, Y', strtotime($evidence->created_at));
            $evidence['deadline'] = date('d M, Y', strtotime($evidence->created_at));
        }
        return response()->json([
            'data' => $evidences,
            'total' => $evidences->count(),
        ], 200);

        foreach ($evidences as $evidence) {
            $evidenceType = $evidence->type;

            $evidenceName = $evidence->name;
            $urlLink = "<a class='btn btn-secondary btn-xs waves-effect waves-light' title='Download' href='" . route('compliance-project-control-evidences-download', [$project->id, $evidence->project_control_id, $evidence->id]) . "'><i class='fe-download' style='font-size:20px;'></i></a>";

            switch ($evidenceType) {
                case 'control':
                    $evidenceName = 'This control is linked to <a href=' . route('project-control-linked-controls-evidences-view', [$project->id, $evidence->path, $evidence->project_control_id]) . ">{$evidence->name}
                                </a>
                                ";
                    $urlLink = "<a href='" . route('project-control-linked-controls-evidences-view', [$project->id, $evidence->path, $evidence->project_control_id]) . "'><i class='fe-link' style='font-size:20px;'></i></a>";
                    break;
                case 'link':
                    $urlLink = "<a href='" . $evidence->path . "' target='_blank'><i class='fe-link' style='font-size:20px;'></i></a>";
                    break;
                default:
                    $urlLink = $urlLink.'';
            }

            $render[] = [
                $evidenceName,
                ucfirst($evidenceType),
                date($this->dateFormat, strtotime($evidence->deadline)),
                date($this->dateFormat, strtotime($evidence->created_at)),
                $urlLink,
            ];
        }
        $draw = $request->draw;
        $response = [
            'draw' => $draw,
            'recordsTotal' => count($evidences),
            'recordsFiltered' => count($evidences),
            'data' => $render,
        ];

        return response()->json($response);
    }


    /**
     * Project Controls.
     **/
    public function ControlsJson(Request $request, Project $project)
    {
        $page = $request->page ?? 1;
        $per_page = $request->per_page ?? 10;
        $keyword = $request->search ?? null;

        // filtering control for only logged-in user
        if (!$this->loggedUser->hasAnyRole($this->globalComplianceAdminRoles)) {
            return $this->getControlsForContributor($project, $keyword, $page, $per_page);
        }

        $projectControlsQuery = ProjectControl::where('project_id', $project->id)
            ->select('*', DB::raw('CONCAT_WS(id_separator, primary_id, sub_id) AS full_control_id'));

        if($keyword !== null){
            $projectControlsQuery2 = clone $projectControlsQuery;
            $projectControlsQuery3 = clone $projectControlsQuery;
            $projectControlsQuery1 = $projectControlsQuery
                ->where('name', 'LIKE', "%{$keyword}%")
                ->orWhere(DB::raw("CONCAT_WS(id_separator, primary_id, sub_id)"), 'LIKE', "%{$keyword}%")
                ->orWhere('description', 'LIKE', '%'.$keyword.'%')
                ->orWhere('status', 'LIKE', '%'.$keyword.'%')
                ->orWhere('automation', 'LIKE', '%'.$keyword.'%')
                ->orWhere('deadline', 'LIKE', '%'.$keyword.'%')
                ->orWhere(DB::raw("(DATE_FORMAT(deadline,'%d-%m-%Y'))"), 'LIKE', '%'.$keyword.'%')
                ->orWhere('frequency', 'LIKE', '%'.$keyword.'%')
                ->orWhereHas('responsibleUser', function ($q) use ($keyword) {
                    $q->where(DB::raw("CONCAT(first_name,' ',last_name)"), 'LIKE', "%{$keyword}%");
                })
                ->orWhereHas('approverUser', function ($q) use ($keyword) {
                    $q->where(DB::raw("CONCAT(first_name,' ',last_name)"), 'LIKE', "%{$keyword}%");
                })->get();

            if(Str::contains($this->notImplemented, strtolower($keyword))){
                $projectControlsQuery3 = $projectControlsQuery3->where(function($q) use ($keyword) {
                    $q->where('applicable', 1);
                    $q->where('status', 'LIKE', '%'.$keyword.'%');
                })->get();
            } else {
                $projectControlsQuery3 = $projectControlsQuery3->where(function($q) use ($keyword) {
                    $q->where('status', 'LIKE', '%'.$keyword.'%');
                })->get();
            }

            if(Str::contains('not applicable', strtolower($keyword))){
                $projectControlsQuery2 = $projectControlsQuery2->where('applicable', 0)->get();
            }
            $projectControlsQuery = $projectControlsQuery3->merge($projectControlsQuery2)->merge($projectControlsQuery1);
        }

        $this->sort(['description', 'automation', 'approver', 'responsible', 'status', 'deadline', 'applicable', 'full_control_id', 'name', 'frequency'], $projectControlsQuery);

        $count = $projectControlsQuery->where('project_id', $project->id)->count();
        if($keyword !== null){
            $controls = $projectControlsQuery->where('project_id', $project->id)->paginate($per_page, $count);
        } else {
            $controls = $projectControlsQuery->where('project_id', $project->id)->skip(--$page * $per_page)->take($per_page)->paginate($per_page);
        }

        return response()->json([
            'total' => $count,
            'data' => $controls
        ]);
    }


    /**
     *
     * gets the controls for contributer(s)
    */
    private function getControlsForContributor($project, $keyword, $page, $per_page)
    {
        $projectControlsQuery = $project->controls()->where(function ($query) {
            $query->where('approver', $this->loggedUser->id)
                ->orWhere('responsible', $this->loggedUser->id);
        });

        if ($keyword) {
            $projectControlsQuery3 = clone $projectControlsQuery;
            $projectControlsQuery->where(function ($query) use ($keyword) {
                $query->where('name', 'LIKE', "%{$keyword}%")
                    ->orWhere(DB::raw("CONCAT_WS(id_separator, primary_id, sub_id)"), 'LIKE', "%{$keyword}%")
                    ->orWhere('description', 'LIKE', '%'.$keyword.'%')
                    // ->orWhere('status', 'LIKE', '%'.$keyword.'%')
                    ->orWhere('deadline', 'LIKE', '%'.$keyword.'%')
                    ->orWhere('frequency', 'LIKE', '%'.$keyword.'%')
                    ->orWhereHas('responsibleUser', function ($q) use ($keyword) {
                        $q->where(DB::raw("CONCAT(first_name,' ',last_name)"), 'LIKE', "%{$keyword}%");
                    })
                    ->orWhereHas('approverUser', function ($q) use ($keyword) {
                        $q->where(DB::raw("CONCAT(first_name,' ',last_name)"), 'LIKE', "%{$keyword}%");
                    });
            });
            if(Str::contains($this->notImplemented, strtolower($keyword))){
                $projectControlsQuery3 = $projectControlsQuery3->where(function($q) use ($keyword) {
                    $q->where('applicable', 1);
                    $q->where('status', 'LIKE', '%'.$keyword.'%');
                })->get();
            } else {
                $projectControlsQuery3 = $projectControlsQuery3->where(function($q) use ($keyword) {
                    $q->where('status', 'LIKE', '%'.$keyword.'%');
                })->get();
            }
            $projectControlsQuery = $projectControlsQuery3->merge($projectControlsQuery);
        }

        $count = $projectControlsQuery->count();
        if($keyword){
            $controls = $projectControlsQuery->paginate($per_page, $count);
        } else {
            $controls = $projectControlsQuery->skip(--$page * $per_page)->take($per_page)->paginate($per_page);
        }

        return response()->json([
            'total' => $count,
            'data' => $controls
        ]);
    }

    private function recordChanges($projectControl, $control, &$changes) {
        $approver = $projectControl->approver;
        $responsible = $projectControl->responsible;

        if($approver && !$control['approver'] || $approver && $approver !== $control['approver']){
            if(!array_key_exists($approver, $changes['approver']['removals'])){
                $changes['approver']['removals'][$approver] = [
                    'full_name' => $projectControl->approverUser->full_name,
                    'email' => $projectControl->approverUser->email,
                    'controls' => []
                ];
            }

            $changes['approver']['removals'][$approver]['controls'] = [
                ...$changes['approver']['removals'][$approver]['controls'],
                $projectControl
            ];
        }

        if($responsible && !$control['responsible'] || $responsible && $responsible !== $control['responsible']){
            // responsible removal
            if(!array_key_exists($responsible, $changes['responsible']['removals'])){
                $changes['responsible']['removals'][$responsible] = [
                    'full_name' => $projectControl->responsibleUser->full_name,
                    'email' => $projectControl->responsibleUser->email,
                    'controls' => []
                ];
            }

            $changes['responsible']['removals'][$responsible]['controls'] = [
                ...$changes['responsible']['removals'][$responsible]['controls'],
                $projectControl
            ];
        }
    }

    /**
     * Control update from project details.
     */
    public function updateAllJson(Request $request, Project $project)
    {
        if (!Auth::guard('admin')->user()->hasAnyRole($this->globalComplianceAdminRoles)) {
            return RegularFunctions::accessDeniedResponse();
        }

        $changes = [
            'responsible' => [
                'assignments' => [],
                'removals' => []
            ],
            'approver' => [
                'assignments' => [],
                'removals' => []
            ],
        ];

        foreach ($request->controls as $control) {

            $projectControl = ProjectControl::findOrFail($control['id']);
            if(!$control['applicable'] && !$projectControl->applicable){
                continue;
            }

            // reset the control if it's not applicable anymore
            if(!$control['applicable'] && $projectControl->applicable){
                $projectControl->update([
                   'responsible' => null,
                   'approver' => null,
                   'deadline' => null,
                   'automation' => 'none',
                   'frequency' => 'Annually',
                   'applicable' => false,
                   'manual_override' => false
                ]);
                continue;
            }

            if($control['applicable'] && !$projectControl->applicable){
                $this->resetAutomation($project, $projectControl);
            }

            // delete the schedule record if the deadline was changed
            if($projectControl->deadline != $control['deadline']) {
                ComplianceProjectTaskScheduleRecord::query()
                    ->whereDate('created_at', today())
                    ->where('name', 'taskDeadlineReminder')
                    ->where('compliance_project_control_id', $projectControl->id)
                    ->delete();
            }

            //delete the compliance schedule record if user change frequency second time
            if ($projectControl->frequency != $control['frequency']) {
                ComplianceProjectTaskScheduleRecord::where('compliance_project_control_id', $projectControl->id)->delete();
            }

            $this->recordChanges($projectControl, $control, $changes);

            // set the responsible with the approver (if needed)
            if(
                $control['responsible'] && $control['automation'] !== 'none'
                || $control['responsible'] && $control['approver'] && $control['automation'] === 'none'
            ){
                $projectControl->update([
                    'responsible' => $control['responsible'],
                    'approver' => $control['automation'] === 'none' ? $control['approver'] : null,
                    'deadline' => $control['deadline'] ?? date('Y-m-d'),
                    'frequency' => $control['frequency'] ?? 'One-Time',
                    'applicable' => true
                ]);
                continue;
            }

            // no needed info was given, no control was set to not applicable,
            // so we reset the responsible and approver
            $projectControl->update([
                'responsible' => null,
                'approver' => null,
                'applicable' => true
            ]);
        }

        foreach ($changes as $role => $change) {
            foreach ($change as $type => $data) {
                foreach ($data as $user_id => $meta) {
                    $subject = $role === 'responsible' ? $this->removalTaskAssignment : $this->removalApprovalResponsibility;
                    $data = [
                        'greeting' => $this->emailContentHello . decodeHTMLEntity($meta['full_name']),
                        'title' => $role === 'responsible' ? $this->removalTaskAssignmentMessage : 'You have been removed as an approver for the following tasks:',
                        'project' => $project,
                        'projectControls' => $meta['controls'],
                        'information' => $this->removalTaskAssignmentAction,
                    ];

                    try {
                        Mail::to($meta['email'])->send(new ControlAssignmentRemoval($data, $subject));
                    } catch (\Throwable $th) {
                        return redirect()->back()->withError("Failed to process request. Please check SMTP authentication connection first.");
                    }
                }
            }
        }

        SystemGeneratedDocsHelpers::checkSystemGeneratedDocuments();
        return response()->json(['message' => 'Controls Updated Successfully']);
    }

    private function resetAutomation($project, $control) {
        $standard_control = StandardControl::query()
            ->where('standard_id', $project->of_standard->id)
            ->where('primary_id', $control->primary_id)
            ->firstWhere('sub_id', $control->sub_id);
        
        if ($standard_control->automation === 'awareness') {
            // restore to awareness automation
            $control->update([
                'automation' => 'awareness'
            ]);
        }

        if(
            $standard_control
            && $standard_control->automation === 'document'
            && $control->automation !== 'document'
            && !$control->manual_override
            && !DocumentTemplate::find($control->document_template_id)->is_generated
        ){
            if(
                Policy::query()
                    ->where('type', 'automated')
                    ->where('path', $control->document_template_id)
                    ->doesntExist()
            ){
                // restore to document automation
                ProjectControl::query()
                    ->where('status', 'Not Implemented')
                    ->where('automation', 'none')
                    ->where('document_template_id', $control->document_template_id)
                    ->whereNull('approver')
                    ->whereNull('responsible')
                    ->whereNull('frequency')
                    ->whereNull('deadline')
                    ->update([
                        'automation' => 'document'
                    ]);
                $template = DocumentTemplate::findOrFail($control->document_template_id);
                Policy::create([
                    'type' => 'automated',
                    'path' => $template->id,
                    'display_name' => $template->name,
                    'description' => $template->description,
                    'version' => '0.1'
                ]);

                if($template->versions()->count() === 0){
                    // create the default one
                    $template->versions()->create([
                        'version' => '0.1',
                        'status' => 'draft',
                        'body' => $template->body,
                        'description' => $template->description,
                        'admin_id' => null,
                        'title' => $template->name
                    ]);
                }
            }
            $is_published = $control->template->published()->exists();
            $last_document = $control->template->versions->last();
            $control->update([
                'automation' => 'document',
                'status' => $is_published ? 'Implemented' : 'Not Implemented',
                'responsible' => $last_document->admin_id,
                'frequency' => 'Annually',
                'deadline' => $is_published ? $last_document->created_at->format('Y-m-d') : now()->addDays(7)->format('Y-m-d'),
                'is_editable' => !$is_published,
                'current_cycle' => $control->template->versions()->where('status', 'published')->count() + 1
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Technical Automation
    |--------------------------------------------------------------------------
    */

    public function updateProjectControlAutomation($project, $projectControlId, Request $request)
    {
       // check if projectControlIs is in array or not. if not then changeing it to array
        $projectControlId = (is_array($projectControlId)) ? $projectControlId : [$projectControlId];
        $projectControls = ProjectControl::withoutGlobalScope(new DataScope)->whereIn('id',$projectControlId)->get();
        if($projectControls){
            foreach($projectControls as $projectControl){
                $approver = $projectControl->approver;
                $responsible = $projectControl->responsible;

                if($projectControl)
                {
                    if (request('automation') === 'none') {
                        if($projectControl->automation !== 'none'){
                            $this->validate($request, [
                                'responsible' => 'required',
                                'deadline' => 'required',
                                'frequency' => 'bail|required|in:One-Time,Monthly,Every 3 Months,Bi-Annually,Annually',
                                'approver' => 'bail|required|exists:admins,id|different:responsible',
                            ]);

                            $approver = $request->approver;
                            $responsible = $request->responsible;
                        }

                        $frequency = $request->frequency;
                        $deadline = $request->deadline;
                        $automation = 'none';
                        $status = 'Not Implemented';
                        $manualOverride = 1;
                    } else {
                        if($projectControl->standardControlAutomation !== 'none'){
                            $approver = null;
                        }

                        $deadline = $projectControl->deadline;
                        $frequency = $projectControl->frequency;
                        $automation = $projectControl->standardControlAutomation;
                        $status = $projectControl->status;
                        $manualOverride = 0;
                        if ($automation === 'technical') {
                            $projectControl->update([
                                'automation' => $automation,
                            ]);
                            $status = $this->getMergedEvidences($project->id, $projectControl->id, true);
                        }
                    }

                    $projectControl->update([
                        'automation' => $automation,
                        'status' => $status,
                        'approver' => $approver,
                        'responsible' => $responsible,
                        'frequency' => $frequency,
                        'deadline' => $deadline,
                        'manual_override' => $manualOverride,
                        'unlocked_at' => null,
                        'current_cycle' => 1,
                        'is_editable' => true
                    ]);
                    $projectControl->refresh();

                    if($projectControl->automation == "document" && !DocumentTemplate::find($projectControl->document_template_id)->is_generated){
                        if(
                            Policy::query()
                            ->where('type', 'automated')
                            ->where('path', $projectControl->document_template_id)
                            ->doesntExist()
                        ){
                            // restore to document automation
                            ProjectControl::query()
                                ->where('automation', 'none')
                                ->where('document_template_id', $projectControl->document_template_id)
                                ->whereNull('approver')
                                ->whereNull('responsible')
                                ->whereNull('frequency')
                                ->whereNull('deadline')
                                ->update([
                                    'automation' => 'document'
                                ]);
                            $template = DocumentTemplate::findOrFail($projectControl->document_template_id);
                            Policy::create([
                                'type' => 'automated',
                                'path' => $template->id,
                                'display_name' => $template->name,
                                'description' => $template->description,
                                'version' => '0.1'
                            ]);

                            if($template->versions()->count() === 0){
                                // create the default one
                                $template->versions()->create([
                                    'version' => '0.1',
                                    'status' => 'draft',
                                    'body' => $template->body,
                                    'description' => $template->description,
                                    'admin_id' => null,
                                    'title' => $template->name
                                ]);
                            }
                        }
                        $is_published = $projectControl->template->published()->exists();
                        $last_document = $projectControl->template->versions->last();
                        $projectControl->update([
                            'automation' => $automation,
                            'status' => $is_published ? 'Implemented' : 'Not Implemented',
                            'responsible' => $last_document->admin_id,
                            'frequency' => 'Annually',
                            'deadline' => $is_published ? $last_document->created_at->format('Y-m-d') : now()->addDays(7)->format('Y-m-d'),
                            'is_editable' => !$is_published,
                            'current_cycle' => $projectControl->template->versions()->where('status', 'published')->count() + 1
                        ]);
                    }

                    // restore to awareness automation
                    if($projectControl->automation == "awareness"){
                        $policyCampaign = null;
                        $policyCampaignAckCount = 0;
                        $campaign = Campaign::withoutGlobalScope(DataScope::class)->where('campaign_type','awareness-campaign')->latest()->first();

                        if($campaign){
                            $policyCampaign = CampaignPolicy::where('campaign_id',$campaign->id)->latest()->first();
                            $policyCampaignAckCount = CampaignAcknowledgment::where('campaign_id',$campaign->id)
                                ->where('policy_id',$policyCampaign->id)
                                ->where('status','completed')
                                ->get()
                                ->count();
                        }

                        $projectControl->update([
                            'automation' => $automation,
                            'status' => $policyCampaignAckCount > 0 ? 'Implemented' : 'Not Implemented',
                            'responsible' => auth()->user()->id,
                            'frequency' => 'One-Time',
                            'deadline' =>  now()->addDays(7)->format('Y-m-d'),
                            'is_editable' => !($policyCampaignAckCount > 0),
                            'current_cycle' => 1
                        ]);
                    }

                }
            } // end of foreach

                if($approver){
                    $responsibleUser = Admin::findorfail($request->responsible);
                    if($responsibleUser && $request->manualOverride !== 'none'){

                        $subject = $this->newTaskAssignment;
                        $data = [
                            'greeting' => $this->emailContentHello . ucwords(decodeHTMLEntity($responsibleUser->full_name)),
                            'title' => str_replace('approver', 'responsibility', $this->mailTitleContent),
                            'project' => $project,
                            'projectControls' => $projectControls,
                            'action' => [
                                'action_title' => '',
                                'action_url' => route('compliance-dashboard'),
                                'action_button_text' => $this->goToDashboard,
                            ],
                            'information' => '',
                        ];
                        try {
                            ProjectControlJob::dispatch($responsibleUser->email,$data,$subject);
                        } catch (\Throwable $th) {
                            return redirect()->back()->withError("Failed to process request. Please check SMTP authentication connection fir.");
                        }
                    }
                    $subject = $this->mailSubject;
                    $data = [
                        'greeting' => $this->emailContentHello . ucwords(decodeHTMLEntity($projectControl->approverUser->full_name)),
                        'title' => $this->mailTitleContent,
                        'project' => $project,
                        'projectControls' => $projectControls,
                        'information' => $this->mailInformationContent,
                    ];
                    if(!empty($data)){
                        ProjectControlJob::dispatch($projectControl->approverUser->email,$data,$subject);
                    }

                }

                SystemGeneratedDocsHelpers::checkSystemGeneratedDocuments();

                Log::info('Project control automation updated', [
                    'automation' => $automation
                ]);
                return back()->with('success', 'Project Control Automation Updated Successfully.');

        } // end of if

        return back()->with('error', 'Project Control Not Found.');
    }

    public function getProjectStat(Request $request,Project $project){
        $data['total'] = $project->controls()->count();
        $data['notApplicable'] = $project->controls()->where('applicable', 0)->count();
        $data['implemented'] = $project->controls()->where('applicable', 1)->where('status', 'Implemented')->count();
        $data['notImplementedcontrols'] = $project->controls()->where('applicable', 1)->where('status', $this->notImplemented)->count();
        $data['rejected'] = $project->controls()->Where('status', 'Rejected')->count();
        $data['notImplemented'] = $data['notImplementedcontrols'] + $data['rejected'];
        $data['underReview'] = $project->controls()->where('applicable', 1)->where('status', $this->underReview)->count();
        $data['perImplemented'] = ($data['total'] > 0) ? ($data['implemented'] / $data['total']) * 100 : 0;
        $data['perUnderReview'] = ($data['total'] > 0) ? ($data['underReview'] / $data['total']) * 100 : 0;
        $data['perNotImplemented'] = ($data['total'] > 0) ? ($data['notImplemented'] / $data['total']) * 100 : 0;

        return response()->json($data);
    }

    /**
     * checking if the user departement is the project department or department below it
     */
    public function checkIfResponsibleOfSameDepartment($id, $project)
    {
        $project_department = $project->department()->first();
        $organizationId = $project_department->organization_id;
        $departmentId = $project_department->department_id;
        $all_child_department_with_own_department = Department::where('parent_id', $departmentId)
            ->orWhere('id', $departmentId)
            ->pluck('id');

        return Admin::where('id', $id)
            ->whereHas('department', function ($query) use ($departmentId, $organizationId, $all_child_department_with_own_department) {
                if ($departmentId == 0 && $organizationId != null)
                {
                    return $query->where('organization_id', $organizationId);
                }
                else
                {
                    return $query->whereIn('department_id', $all_child_department_with_own_department);
                }
            })
            ->exists();
    }
}
