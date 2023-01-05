<?php

namespace App\Http\Controllers\Compliance\Project;

use App\Exports\Project\ProjectExport;
use App\Helpers\SystemGeneratedDocsHelpers;
use App\Http\Controllers\Controller;
use App\Jobs\AutoMapControlJob;
use App\Jobs\TechnicalControlMap;
use App\Mail\Compliance\ControlAssignmentRemoval;
use App\Mail\Compliance\ProjectNameUpdateNotification;
use App\Models\Compliance\Project;
use App\Models\Compliance\ProjectControl;
use App\Models\Compliance\ComplianceProjectControlHistoryLog;
use App\Models\Compliance\Standard;
use App\Models\DocumentAutomation\DocumentTemplate;
use App\Models\PolicyManagement\Policy;
use App\Models\RiskManagement\RiskMappedComplianceControl;
use App\Models\RiskManagement\RiskRegister;
use App\Rules\common\UniqueWithinDataScope;
use App\Traits\DataScopeAccessCheckTrait;
use App\Traits\Integration\IntegrationApiTrait;
use App\Utils\RegularFunctions;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\PolicyManagement\Campaign\Campaign;
use App\Models\PolicyManagement\Campaign\CampaignPolicy;
use App\Models\PolicyManagement\Campaign\CampaignAcknowledgment;
use App\Models\Compliance\StandardControl;
use App\Models\GlobalSettings\GlobalSetting;
use App\Models\RiskManagement\RiskRegisterHistoryLog;
use Illuminate\Support\Facades\Session;

class ProjectController extends Controller
{
    use IntegrationApiTrait;

    protected $loggedUser;
    protected $viewBasePath = 'compliance.projects.';

    use DataScopeAccessCheckTrait;

    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware(function ($request, $next) {
            $this->loggedUser = Auth::guard('admin')->user();

            return $next($request);
        });

        \View::share('standards', RegularFunctions::getAllStandards());
    }

    public function view(Request $request)
    {
        /* Sharing page title to view */
        view()->share('pageTitle', 'View Projects');

        return Inertia::render('compliance/project-list-page/ProjectListPage');
    }

    /***
     * @retun html
     * get list of projects
     */
    protected function getProjectList(Request $request)
    {
        $request->validate([
            'data_scope' => 'required'
        ]);

        $projectBaseQuery = Project::where(function ($query) use ($request) {
            if ($request->project_name) {
                $query->where('name', 'like', '%' . $request->project_name . '%');
            }
        })->withCount("controls")
            ->withCount("applicableControls")
            ->withCount("implementedControls")
            ->withCount("notImplementedControls");


        if ($this->loggedUser->hasAnyRole(['Global Admin', 'Compliance Administrator'])) {
            $projects = $projectBaseQuery->orderBy('id', 'DESC')->get();
        } else {
            $projects = $projectBaseQuery->whereHas('controls', function ($q) {
                $q->where('approver', $this->loggedUser->id);
                $q->orWhere('responsible', $this->loggedUser->id);
            })->orderBy('id', 'DESC')->get();
        }

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'data' => $projects,
            ]);
        }
    }

    /***
     * @retun Create project form
     *
     */
    public function create()
    {
        $project = new Project();
        Log::info('User is attempting to create a compliance project.');

        return Inertia::render('compliance/project-create-page/ProjectCreatePage', ['project' => $project]);
    }

    /**
     * Method edit
     *
     * @param Request $request [explicite description]
     * @param $id $id [explicite description]
     *
     * @return void
     */
    public function edit(Request $request, $id)
    {
        $project = Project::findOrFail($id);

        $this->checkDataScopeAccess($project);

        $assignedControls = $project->controls()->whereNotNull('responsible')->whereNotNull('approver')->count();
        Log::info('User is attempting to edit a compliance project.', [
            'project_id' => $id
        ]);

        return Inertia::render('compliance/project-create-page/ProjectCreatePage', ['project' => $project, 'assignedControls' => $assignedControls]);
    }

    /**
     * get edit data
     */
    public function getEditData(Request $request, $id)
    {
        $project = Project::findOrFail($id);
        $assignedControls = $project->controls()->whereNotNull('responsible')->whereNotNull('approver')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'project' => $project,
                'assignedControls' => $assignedControls
            ]
        ]);
    }

    /*
    * Creates a new project
    */
    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => ['required', 'max:190', new UniqueWithinDataScope(new Project, 'name')],
            'description' => 'required',
            'standard_id' => 'required|numeric',
        ], [
            'name.required' => 'The Project Name field is required.',
            'description.required' => 'The Description field is required.',
            'standard_id.required' => 'The Standard field is required.',
        ]);

        $inputs = $request->all();
        $project = \DB::transaction(function () use ($request, $inputs) {
            $standard = Standard::findorfail($request->standard_id);
            $admin_id = auth()->id();
            /*Creating project*/
            $project = Project::create([
                'standard_id' => $standard->id,
                'admin_id' => $admin_id,
                'standard' => $standard->name,
                'name' => $inputs['name'],
                'description' => $inputs['description'],
            ]);

            $controls = $standard->controls()->get(['index', 'name', 'primary_id', 'sub_id', 'id_separator', 'description', 'required_evidence', 'automation', 'document_template_id']);
            $controls = $controls->map(function ($control) use ($admin_id) {
                if ($control->automation === 'document') {
                    $control->deadline = now()->addDays(7)->format('Y-m-d');
                    $control->frequency = 'Annually';
                    $control->current_cycle = $control->template->versions()->where('status', 'published')->count() + 1;
                }
                if ($control->automation === 'technical') {
                    $control->deadline = now()->addDays(7)->format('Y-m-d');
                    $control->frequency = 'One-Time';
                    $control->responsible = $admin_id;
                }
                if ($control->automation === 'awareness') {
                    $control->deadline = now()->addDays(7)->format('Y-m-d');
                    $control->frequency = 'One-Time';
                }
                return $control;
            });
            $controls = $controls->toArray();
            /*Creating project controls*/
            $project->controls()->createMany($controls);

            // To create data in compliance_project_controls_history_log from here instead of from observer, to reduce database query
            $projectControls = ProjectControl::where('project_id', $project->id)->get();
            $todayDate = RegularFunctions::getTodayDate();
            $controlsForHistory = $projectControls->map(function ($control) use ($project, $todayDate) {
                return [
                    'project_id' => $project->id,
                    'control_id' => $control->id,
                    'applicable' => $control->applicable,
                    'log_date' => $todayDate,
                    'status' => $control->status,
                    'control_created_date' => $control->created_at,
                    'control_deleted_date' => $control->deleted_at,
                    'deadline' => $control->deadline,
                    'frequency' => $control->frequency,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];
            });

            ComplianceProjectControlHistoryLog::insert($controlsForHistory->toArray());

            return $project;
        });

        // restore to awareness automation
        $policyCampaign = null;
        $policyCampaignAckCount = 0;
        $campaign = Campaign::withoutGlobalScopes()->where('campaign_type', 'awareness-campaign')->latest()->first();
        $projectStandardContorls = StandardControl::select('id', 'primary_id', 'id_separator', 'sub_id')->where('standard_id', $request->standard_id)->where('automation', 'awareness')->get();
        $controlList = $project->controls()->get();

        if ($campaign) {
            //if campaign have been run then assigning responsible to the awareness control of new project
            foreach ($projectStandardContorls as $control) {
                $toAssignResponsible = $controlList->where('primary_id', $control->primary_id)->where('id_separator', $control->id_separator)->where('sub_id', $control->sub_id)->first();
                $toAssignResponsible->responsible = Auth::guard('admin')->user()->department->department_id == $campaign->department->department_id ? $campaign->owner_id : Auth::guard('admin')->user()->id;
                $toAssignResponsible->frequency = 'One-Time';
                $toAssignResponsible->is_editable = false;
                $toAssignResponsible->save();

                // Update frequency in compliance_project_controls_history_log also,
                // project control is just created so don't need to check log_date in compliance_project_controls_history_log table
                ComplianceProjectControlHistoryLog::where('control_id', $toAssignResponsible->id)->update(['frequency' => 'One-Time']);
            }

            $policyCampaign = CampaignPolicy::where('campaign_id', $campaign->id)->latest()->first();
            $policyCampaignAckCount = CampaignAcknowledgment::where('campaign_id', $campaign->id)
                ->where('policy_id', $policyCampaign->id)
                ->where('status', 'completed')
                ->get()
                ->count();
        }
        //if campaign have been ackowledge then implementing the awareness control status of new project
        if ($policyCampaignAckCount > 0) {
            foreach ($projectStandardContorls as $control) {
                $toImplement = $controlList->where('primary_id', $control->primary_id)->where('id_separator', $control->id_separator)->where('sub_id', $control->sub_id)->first();
                $toImplement->status = "Implemented";
                $toImplement->save();

                // Update status in compliance_project_controls_history_log also
                // project control is just created so don't need to check log_date in compliance_project_controls_history_log table
                ComplianceProjectControlHistoryLog::where('control_id', $toImplement->id)->update(['status' => 'Implemented']);
            }
        }

        // restore to document automation
        $document_automated_controls = $project->controls()->whereNotNull('document_template_id')->get();
        $document_template_ids = $document_automated_controls->pluck('document_template_id');

        ProjectControl::query()
            ->where('automation', 'none')
            ->whereIn('document_template_id', $document_template_ids)
            ->whereNull('approver')
            ->whereNull('responsible')
            ->whereNull('frequency')
            ->whereNull('deadline')
            ->update([
                'automation' => 'document',
                'frequency' => 'Annually'
            ]);

        $project->of_standard->controls()->where('automation', 'none')->whereIn('document_template_id', $document_template_ids)->update(['automation' => 'document']);

        DocumentTemplate::query()
            ->whereIn('id', $document_template_ids)
            ->where('is_generated', false)
            ->each(function ($template) {
                if (!$template->versions()->exists()) {
                    $template->versions()->create([
                        'title' => $template->name,
                        'admin_id' => null,
                        'body' => $template->body,
                        'description' => $template->description,
                        'version' => '0.1',
                        'status' => 'draft'
                    ]);
                    Policy::create([
                        'display_name' => $template->name,
                        'type' => 'automated',
                        'path' => $template->id,
                        'version' => '0.1',
                        'description' => $template->description
                    ]);
                }
            });

        $implemented_controls = $document_automated_controls->filter(function ($control) {
            return $control->template->versions()->where('status', 'published')->exists();
        });

        if ($implemented_controls) {
            DB::transaction(function () use ($implemented_controls) {
                foreach ($implemented_controls as $control) {
                    $document = $control->template->versions->last();
                    $control->status = "Implemented";
                    $control->responsible = $document->admin_id;

                    $controlDate = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $control->created_at)
                        ->format('Y-m-d');
                    $documentDate = \Carbon\Carbon::createFromFormat('Y-m-d H:i:s', $document->created_at)
                        ->format('Y-m-d');

                    if ($documentDate < $controlDate) {
                        $controlDeadline = $control->created_at->format('Y-m-d');
                    } else {
                        $controlDeadline = $document->created_at->format('Y-m-d');
                    }
                    $control->deadline = $controlDeadline;
                    $control->frequency = 'Annually';
                    $control->is_editable = false;
                    $control->save();

                    // Update data in compliance_project_controls_history_log also
                    $control->controlHistory->last()->update([
                        'status' => 'Implemented',
                        'deadline' => $controlDeadline,
                        'frequency' => 'Annually'
                    ]);
                }
            });
        }

        callArtisanCommand('technical-control:api-map');

        if ($request->project_to_map) {
            $project_to_map = Project::find($request->project_to_map);
            AutoMapControlJob::dispatch($project, $project_to_map);
        }

        Log::info('User has created a compliance project.', ['project_id' => $project->id]);

        SystemGeneratedDocsHelpers::checkSystemGeneratedDocuments();

        if ($request->project_to_map) {
            return redirect(route('compliance-project-show', $project->id))->with('success', 'Your project has been created successfully. Control mapping is in progress in the background and will complete shortly.');
        }

        return redirect(route('compliance-project-show', $project->id));
    }

    /***
     * Update a project
     */
    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'name' => ['required', 'max:190', new UniqueWithinDataScope(new Project, 'name', $id)],
            'description' => 'required',
        ], [
            'name.required' => 'The Project Name field is required.',
            'description.required' => 'The Description field is required.',
        ]);

        $project = Project::findOrFail($id);
        $currentProjectName = decodeHTMLEntity($project->name);
        $newProjectName = $request->name;

        $assignedControls = ProjectControl::where('project_id', $project->id)->whereNotNull('responsible')->whereNotNull('approver')->get();

        //checking if old project name and new project name is not same and send notification to users
        if ($currentProjectName != $newProjectName && $assignedControls->count() > 0) {
            /* Getting unique control by responsible and approvar */
            $uniqueResponsibleProjectControls = $assignedControls->unique('responsible');
            $uniqueApproverProjectControls = $assignedControls->unique('approver');

            /* Sending responsible users mail notification of update project name */
            foreach ($uniqueResponsibleProjectControls as $uniqueResponsibleProjectControl) {
                $controls = $assignedControls->where('responsible', $uniqueResponsibleProjectControl->responsible);

                $data = [
                    'greeting' => 'Hello ' . decodeHTMLEntity($uniqueResponsibleProjectControl->responsibleUser->full_name),
                    'title' => 'The name of the Project ' . '<b> ' . $currentProjectName . '</b>' . ' has been renamed to ' . '<b> ' . decodeHTMLEntity($newProjectName) . '</b>' . '. The control(s) for which you are assigned as responsible for are listed below:',
                    'project' => $project,
                    'standard' => $project->standard,
                    'projectName' => $newProjectName,
                    'projectControls' => $controls,
                ];

                Mail::to($uniqueResponsibleProjectControl->responsibleUser->email)->send(new ProjectNameUpdateNotification($data));
            }

            /* Sending approver users mail notification of update project name */
            foreach ($uniqueApproverProjectControls as $uniqueApproverProjectControl) {
                $controls = $assignedControls->where('approver', $uniqueApproverProjectControl->approver);

                $data = [
                    'greeting' => 'Hello ' . decodeHTMLEntity($uniqueApproverProjectControl->approverUser->full_name),
                    'title' => 'The name of the Project ' . '<b> ' . decodeHTMLEntity($currentProjectName) . '</b>' . ' has been renamed to ' . '<b> ' . decodeHTMLEntity($newProjectName) . '</b>' . '. The control(s) for which you are assigned as an approver are listed below:',
                    'project' => $project,
                    'standard' => $project->standard,
                    'projectName' => $newProjectName,
                    'projectControls' => $controls,
                ];

                Mail::to($uniqueApproverProjectControl->approverUser->email)->send(new ProjectNameUpdateNotification($data));
            }
        }

        $input = $request->all();

        $toBeUpDatedInput = [
            "name" => $input['name'],
            "description" => $input['description'],
            "data_scope" => $input['data_scope']
        ];

        $project->update($toBeUpDatedInput);

        Log::info('User has updated a compliance project.', ['project_id' => $id]);

        return redirect()->route('compliance-project-show', $project->id);
    }

    /***
     * Deletes the project
     */
    public function delete(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $project = Project::findOrFail($id);
            $assignedControls = ProjectControl::where('project_id', $project->id)->whereNotNull('responsible')->whereNotNull('approver')->get();

            if ($assignedControls->count() > 0) {
                /* Getting unique control by responsible and approvar */
                $uniqueResponsibleProjectControls = $assignedControls->unique('responsible');
                $uniqueApproverProjectControls = $assignedControls->unique('approver');

                /* Sending responsible users mail notification of un-assigment */
                foreach ($uniqueResponsibleProjectControls as $uniqueResponsibleProjectControl) {
                    $controls = $assignedControls->where('responsible', $uniqueResponsibleProjectControl->responsible);
                    $subjects = 'Removal of task assignment';
                    $data = [
                        'greeting' => 'Hello ' . decodeHTMLEntity($uniqueResponsibleProjectControl->responsibleUser->full_name),
                        'title' => 'You have been removed responsibility for providing evidence for the following tasks:',
                        'project' => $project,
                        'projectControls' => $controls,
                        'information' => "This is an informational email and you don't have to take any action.",
                    ];

                    Mail::to($uniqueResponsibleProjectControl->responsibleUser->email)->send(new ControlAssignmentRemoval($data, $subjects));
                }

                /* Sending approver users mail notification of un-assigment */
                foreach ($uniqueApproverProjectControls as $uniqueApproverProjectControl) {
                    $controls = $assignedControls->where('approver', $uniqueApproverProjectControl->approver);
                    $subjects = 'Removal of approval responsibility';
                    $data = [
                        'greeting' => 'Hello ' . decodeHTMLEntity($uniqueApproverProjectControl->approverUser->full_name),
                        'title' => 'You have been removed as an approver from the following tasks:',
                        'project' => $project,
                        'projectControls' => $controls,
                        'information' => "This is an informational email and you don't have to take any action.",
                    ];

                    Mail::to($uniqueApproverProjectControl->approverUser->email)->send(new ControlAssignmentRemoval($data, $subjects));
                }
            }

            // opening risk again if mapped
            // when risk is opened the residual score is also restored to inherent_score
            // since complaince project is deleted with all controls thus,
            // link to those controls in RiskMappedComplianceControl,
            // is also removed for both soft deleted risks along
            //  with soft deleted links are also opened up as control is deleted from Complaince project delete
            $this->unlinkAndOpenRiskAfterProjectDelete($project);

            if (isset($project->of_standard)) {
                if ($project->of_standard->projects->count() - 1 === 0) {
                    // the last project in that standard
                    // delete the policies and versions
                    $templates = $project
                        ->controls()
                        ->whereNotNull('document_template_id')
                        ->whereDoesntHave('template.versions', function ($query) {
                            return $query->where('status', 'published');
                        })
                        ->pluck('document_template_id');
                    //check if other projects don't require this doc templates;
                    $required_by_other = ProjectControl::where('project_id', '!=', $project->id)
                        ->whereNotNull('document_template_id')
                        ->whereIn('document_template_id', $templates)
                        ->pluck('document_template_id');


                    //if in templates id to be deleted and also in required by others ids, make the difference and only the ones that are not in required by other should remain
                    $templates = $templates->diff($required_by_other)->toArray();

                    Policy::query()->where('type', 'automated')->whereIn('path', $templates)->delete();
                    DocumentTemplate::query()->findMany($templates)->each(function ($document) {
                        $document->versions()->delete();
                    });
                }
            }
            $project->delete();

            $todayDate = RegularFunctions::getTodayDate();
            ComplianceProjectControlHistoryLog::where('project_id', $id)->where('log_date', $todayDate)->delete();
            $controls = ProjectControl::where('project_id', $id)
                ->get()->map(function ($control) use ($todayDate) {
                    return [
                        'project_id' => $control->project_id,
                        'control_id' => $control->id,
                        'applicable' => $control->applicable,
                        'status' => $control->status,
                        'deadline' => $control->deadline,
                        'frequency' => $control->frequency,
                        'log_date' => $todayDate,
                        'control_created_date' => $control->created_at->format('Y-m-d'),
                        'control_deleted_date' => $todayDate,
                        'created_at' => $control->created_at,
                        'updated_at' => $control->updated_at,
                    ];
                })->toArray();
            ComplianceProjectControlHistoryLog::insert($controls);

            ProjectControl::where('project_id', $id)->delete();

            DB::commit();
            Log::info('User has deleted a compliance project.', ['project_id' => $id]);
            return redirect()->back()->with(['success' => 'Project deleted successfully.']);
        } catch (\Swift_TransportException $e) {
            DB::rollBack();
            Session::flash(
                'error',
                'Failed to delete project due to SMTP server error! Please check your SMTP connection.'
            );
            return redirect()->back()
                ->withErrors([
                    'smtpError' => 'SMTP connection failed'
                ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Session::flash('error', 'Project delete error.');
            return redirect()->back()->withErrors(['error','Project delete error.']);
        }
    }

    private function unlinkAndOpenRiskAfterProjectDelete($project)
    {
        try {
            $controlIds = $project->controls->pluck('id')->toArray();

            //getAll the mapped Risk
            $riskToControl = RiskMappedComplianceControl::whereIn('control_id', $controlIds)->get();

            $mappedRisk = $riskToControl->pluck('risk_id')->toArray();

            //get list of changes to be made so that we dont have to loop in datas later for risk reg history
            $toUpdateRisk = RiskRegister::withTrashed()->whereIn('id', $mappedRisk)
                ->where('treatment_options', 'Mitigate')
                ->where('status', 'Close')->get()->toArray();

            //update risks that have link to the control
            RiskRegister::withTrashed()->whereIn('id', $mappedRisk)
                ->where('treatment_options', 'Mitigate')
                ->where('status', 'Close')
                ->update([
                    'status' => 'Open',
                    'residual_score' => DB::raw('inherent_score'),
                ]);

            //updateOrCreate riskRegisterHistorylog data
            $globalSettings = GlobalSetting::first();
            $nowDateTime = new \DateTime('now', new \DateTimeZone($globalSettings->timezone));
            $todayDate = $nowDateTime->format('Y-m-d');

            array_map(function ($risk) use ($todayDate) {
                $riskChangeLog = RiskRegisterHistoryLog::where('log_date', $todayDate)
                    ->where('risk_register_id', $risk['id'])
                    ->first();
                $changeLogData = [
                    'status' => 'Open',
                    'residual_score' => $risk['inherent_score'],
                ];

                if (!is_null($riskChangeLog)) {
                    $riskChangeLog->update($changeLogData);
                } else {
                    $changeLogData['project_id'] = $risk['project_id'];
                    $changeLogData['risk_register_id'] = $risk['id'];
                    $changeLogData['category_id'] = $risk['category_id'];
                    $changeLogData['log_date'] = $todayDate;
                    $changeLogData['risk_created_date'] = date('Y-m-d H:i:s', strtotime($risk['created_at']));
                    $changeLogData['status'] = $risk['status'];
                    $changeLogData['likelihood'] = $risk['likelihood'];
                    $changeLogData['impact'] = $risk['impact'];
                    $changeLogData['inherent_score'] = $risk['inherent_score'];
                    $changeLogData['residual_score'] = $risk['residual_score'];
                    $changeLogData['is_complete'] = is_null($risk['is_complete']) ? '0' : $risk['is_complete'];
                    RiskRegisterHistoryLog::create($changeLogData);
                }
            }, $toUpdateRisk);
            // unlink the controls from risk by deleting relationship
            $riskToControl->each->delete();
        } catch (\Exception $e) {
            return $e;
        }
    }

    public function checkProjectNameTaken(Request $request, $projectId = null)
    {
        $validator = Validator::make($request->all(), [
            'name' => [new UniqueWithinDataScope(new Project, 'name', $projectId)],
        ]);

        if ($validator->fails()) {
            return 'false';
        } else {
            return 'true';
        }
    }

    public function projectExport(Request $request)
    {
        $fileName = 'Compliance Project ' . date('d-m-Y') . '.xlsx';
        Log::info('User has exported a compliance project.', ['project_id' => $request->id]);

        return Excel::download(new ProjectExport($request->id), $fileName);
    }

    public function getProjectDataForOption(Request $request)
    {
        $enabled_standards = $this->getEnabledMappingStandards();
        if (in_array($request->standard_id, $enabled_standards)) {
            $projects = Project::select('id', 'name')->whereHas('complianceStandard', function ($query) use ($request, $enabled_standards) {
                $query->where('id', '<>', $request->standard_id)
                    ->whereIn('id', $enabled_standards);
            })->get();
        } else {
            $projects = [];
        }

        return response()->json($projects);
    }

    public function getEnabledMappingStandards()
    {
        $enabled = [
            'ISO/IEC 27001-2:2013',
            'UAE IA',
            'ISR V2',
            'SAMA Cyber Security Framework',
            'NCA ECC-1:2018',
            'NCA CSCC-1:2019',
            'SOC 2',
            'PCI DSS 3.2.1',
            'GDPR',
            'PCI DSS 4.0',
            'HIPAA Security Rule',
            'CIS Critical Security Controls Group 1',
            'CIS Critical Security Controls Group 2',
            'CIS Critical Security Controls Group 3',
        ];
        return Standard::whereIn('name', $enabled)->pluck('id')->toArray();
    }
}
