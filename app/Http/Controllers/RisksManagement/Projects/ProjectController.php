<?php

namespace App\Http\Controllers\RisksManagement\Projects;

use App\Models\AssetManagement\Asset;
use Auth;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Rules\ValidDataScope;
use App\Utils\RegularFunctions;
use App\Models\DataScope\DataScope;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\RiskManagement\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;
use App\Models\RiskManagement\RiskCategory;
use App\Models\RiskManagement\RiskRegister;
use App\Models\RiskManagement\RiskRegisterHistoryLog;
use App\Models\GlobalSettings\GlobalSetting;
use App\Rules\common\UniqueWithinDataScope;
use App\Models\RiskManagement\RiskMatrix\RiskScoreLevel;
use App\Models\RiskManagement\RiskMatrix\RiskMatrixScore;
use App\Models\RiskManagement\RiskMatrix\RiskMatrixImpact;
use App\Models\RiskManagement\RiskMatrix\RiskScoreLevelType;
use App\Models\RiskManagement\RiskMatrix\RiskMatrixLikelihood;
use App\Traits\DataScopeAccessCheckTrait;

class ProjectController extends Controller
{
    use DataScopeAccessCheckTrait;
    protected $loggedUser;

    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware(function ($request, $next) {
            $this->loggedUser = Auth::guard('admin')->user();

            return $next($request);
        });

        \View::share('standards', RegularFunctions::getAllStandards());
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        /* Sharing page title to view */
        view()->share('pageTitle', 'View Risk Projects');
        return Inertia::render('risk-management/project/ProjectList');
    }

    /*
    * Creates a new project
    */
    public function store(Request $request)
    {
        $this->validate($request, [
            'name' => ['required','max:190', new UniqueWithinDataScope(new Project, 'name')],
            'description' => 'required',
        ], [
            'name.required' => 'The Project Name field is required',
            'description.required' => 'The Description field is required',
        ]);

        $inputs = $request->all();
        $project = \DB::transaction(function () use ($request, $inputs) {

            /*Creating project*/
            $project = Project::create([
                'owner_id' => auth()->id(),
                'name' => $inputs['name'],
                'description' => $inputs['description'],
            ]);

            return $project;
        });

        Log::info('User has created a Risk project.', ['project_id' => $project->id]);

        return redirect(route('risks.projects.project-show', $project->id))->with(['activeTab' => 'RiskSetup']);
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, Project $project, $tab = 'Details')
    {
        $this->checkDataScopeAccess($project);

        /* Access control */
        if (!$this->loggedUser->hasAnyRole(['Global Admin', 'Risk Administrator'])) {
            // $assignedProjectCount = Project::where('id', $id)->whereHas('controls', function ($q) {
            //     $q->where('approver', $this->loggedUser->id);
            //     $q->orWhere('responsible', $this->loggedUser->id);
            // })->count();

            // if ($assignedProjectCount == 0) {
            //     return RegularFunctions::accessDeniedResponse();
            // }
            // $control_disabled = true;
        } else {
            // $control_disabled = false;
        }
        $controls = [];
        // return view('compliance.projects.controls.index', compact('project'));
        $data = [];
        // $data['total'] = $project->controls()->count();
        // $data['notApplicable'] = $project->controls()->where('applicable', 0)->count();
        // $data['implemented'] = $project->controls()->where('applicable', 1)->where('status', 'Implemented')->count();
        // $data['notImplementedcontrols'] = $project->controls()->where('applicable', 1)->where('status', 'Not Implemented')->count();
        // $data['rejected'] = $project->controls()->Where('status', 'Rejected')->count();
        // $data['notImplemented'] = $data['notImplementedcontrols'] + $data['rejected'];
        // $data['underReview'] = $project->controls()->where('applicable', 1)->where('status', 'Under Review')->count();
        // $data['perImplemented'] = ($data['total'] > 0) ? ($data['implemented'] / $data['total']) * 100 : 0;
        // $data['perUnderReview'] = ($data['total'] > 0) ? ($data['underReview'] / $data['total']) * 100 : 0;
        // $data['perNotImplemented'] = ($data['total'] > 0) ? ($data['notImplemented'] / $data['total']) * 100 : 0;
        // $project=Project::where('id',$id)->first();
        $risksAffectedProperties['common'] = [
            'Confidentiality', 'Integrity', 'Availability',
        ];
        $risksAffectedProperties['other'] = [
            'Change Management', 'Ethical', 'Financial', 'Financial Reporting', 'Fraud', 'Geographic', 'IT Operations', 'Logical Access', 'Material Misstatement', 'Operational', 'Privacy', 'Regulatory / Compliance', 'Reputational', 'Strategy',
        ];
        $riskMatrixImpacts = RiskMatrixImpact::query()->select('name')->get()->pluck('name');
        $riskMatrixLikelihoods = RiskMatrixLikelihood::query()->select('name')->get()->pluck('name');
        $riskMatrixScores = RiskMatrixScore::query()->orderBy('likelihood_index', 'ASC')
            ->orderBy('impact_index', 'ASC')->select(['score', 'likelihood_index', 'impact_index'])->get()->split(count($riskMatrixLikelihoods));
        $riskScoreActiveLevelType = RiskScoreLevelType::where('is_active', 1)->with('levels')->first();
        $riskLikelihoods = RiskMatrixLikelihood::all();
        $riskImpacts = RiskMatrixImpact::all();
        $riskCategories = RiskCategory::all();

        $req=new Request();
        $req['project_id']=$project->id;
        $dashboard_data=app('App\Http\Controllers\RisksManagement\Dashboard\DashboardController')->getDashboardDataJson($req);
        $firstRiskDate = RiskRegister::withTrashed()->where('project_id', $project->id)->orderBy('created_at', 'asc')->pluck('created_at')->first();
        $firstRiskDate = date('Y-m-d', strtotime($firstRiskDate));
        $today = date('Y-m-d');
        
        return Inertia::render('risk-management/project/project-details/ProjectDetails',
        [
            'project' => $project,
            'tab' => ucfirst($tab),
            'risksAffectedProperties' => $risksAffectedProperties,
            'riskMatrixImpacts' => $riskMatrixImpacts,
            'riskMatrixLikelihoods' => $riskMatrixLikelihoods,
            'riskMatrixScores' => $riskMatrixScores,
            'riskScoreActiveLevelType' => $riskScoreActiveLevelType,
            'riskLikelihoods' => $riskLikelihoods,
            'riskImpacts' => $riskImpacts,
            'riskCategories' =>  $riskCategories,
            'dashboardData'=>$dashboard_data->getData()->data,
            'assets' => Asset::select('name','id')->get()->map(function ($asset) {
                return [
                  'label' => $asset->name,
                  'value' => $asset->id
                ];
            }),
            'firstRiskDate' => $firstRiskDate,
            'today' => $today
        ]
        );
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

        Log::info('User is attempting to edit a risk project.', [
            'project_id' => $id
            ]);

        return Inertia::render('risk-management/project/project-create-page/ProjectCreatePage', ['project' => $project]);
    }

    /***
     * Update a project
     */
    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'name' => ['required','max:190', new UniqueWithinDataScope(new Project, 'name', $id)],
            'description' => 'required',
        ], [
            'name.required' => 'The Project Name field is required',
            'description.required' => 'The Description field is required',
        ]);


        $project = Project::findOrFail($id);
        $currentProjectName = decodeHTMLEntity($project->name);
        $newProjectName = $request->name;


        $input = $request->all();
        $toBeUpDatedInput = [
            "name" => $input['name'],
            "description" => $input['description'],
            "data_scope" => $input['data_scope']
        ];
        $updatedProject = $project->update($toBeUpDatedInput);

        Log::info('User has updated a risk project.', ['project_id' => $id]);

        return redirect()->route('risks.projects.project-show', $project->id);
    }

   /***
     * Deletes the project
     */
    public function delete(Request $request, $id)
    {
        $this->deleteRiskRegister($id);
        $project = Project::findOrFail($id);
        $project->delete();
        Log::info('User has deleted a risk project.', ['project_id' => $id]);

        // To Update the RiskRegisterHistoryLog data of the deleted project.
        $this->riskRegisterHistoryLogOfProject($id);

        return redirect()->back()->with(['success' => 'Project deleted successfully.']);
    }

    /***
     * @retun Create project form
     *
     */
    public function create()
    {
        $project = new Project();
        Log::info('User is attempting to create a risk project.');

        return Inertia::render('risk-management/project/project-create-page/ProjectCreatePage', ['project' => $project]);
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

        $projectBaseQuery = Project::withCount('risk_registers')->where(function ($query) use ($request) {
            if ($request->project_name) {
                $query->where('name', 'like', '%'.$request->project_name.'%');
            }
        });

        // ->withCount("applicableControls")
        //     ->withCount("implementedControls")
        //         ->withCount("notImplementedControls");

        if ($this->loggedUser->hasAnyRole(['Global Admin', 'Risk Administrator'])) {
            $projects = $projectBaseQuery->orderBy('id', 'DESC')->get();
            foreach($projects as $project){
                $project_risk_level_count=$this->getProjectRiskDetails($project->id);
                $project['risk_level_count']=$project_risk_level_count;
                $closed_risk_count=$this->GetClosedRiskCount($project->id);
                $project['risk_closed']=$closed_risk_count;
                $project['risk_closed_count']=RiskRegister::where('project_id', '=',  $project->id)->where('status', 'Close')->count();
            }
        }

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'data' => $projects,
            ]);
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

    public function checkProjectRisks(Project $project)
    {
        $risks_count = $project->risk_registers()->count();

        return response()->json([
            'has_risks' => $risks_count > 0,
            'count' => $risks_count
        ]);
    }

    /**
     * Method projectFilterData
     *
     * @param Request $request [explicite description]
     *
     * @return void
     */
    public function projectFilterData(Request $request)
    {
        $request->validate([
            'data_scope' => ['required', new ValidDataScope],
            'selected_departments' => 'nullable'
        ]);

        $dataScope = explode('-', request('data_scope'));
        $selectedDepartments = explode(',', request('selected_departments'));
        $organizationId = $dataScope[0];
        $departmentId = $dataScope[1];

        /* When data scope selected is department */
        if ($departmentId != 0) {
            array_push($selectedDepartments, $departmentId);
        }

        $projects = Project::withoutGlobalScope(DataScope::class)->whereHas('department', function ($query) use ($selectedDepartments) {
            $query->where(function ($query) use ($selectedDepartments) {
                $query
                    ->whereIn('department_id', $selectedDepartments);

                if(in_array('0', $selectedDepartments)){
                    $query->orWhereNull('department_id');
                }
            });
        })->get();

        return response()->json([
            'success' => true,
            'data' => $projects
        ]);
    }

    // Deleting Risk Register of project
    protected function deleteRiskRegister($project_id)
    {
        RiskRegister::where('project_id',$project_id)->whereNull('deleted_at')->delete();
    }



    public function getProjectRiskDetails($id){
        $riskCountWithinRiskLevelForCategories = [];
        $riskCountWithinRiskLevels = [];
        $riskLevelColors = [];
        $riskLevelsList = [];
        $closedRiskCountOfDifferentLevels = [];

        $riskLevels = RiskScoreLevel::whereHas('levelTypes', function ($query) {
            $query->where('is_active', 1);
        })->get();

        foreach ($riskLevels as $riskLevelIndex => $riskLevel) {
            $startScore =  $riskLevelIndex == 0 ? 0 : $riskLevels[$riskLevelIndex-1]->max_score + 1;
            $endScore = $riskLevels[$riskLevelIndex]->max_score;
            $isLastRiskLevelIndex = $riskLevels->keys()->last() == $riskLevelIndex;

                /* Creating risk count within different levels */
                /* closed Risk Of DifferentLevels */
                if (!$isLastRiskLevelIndex) {
                    $riskCountWithinRiskLevel = RiskRegister::whereBetween('residual_score', [$startScore, $endScore])
                    ->when($id, function (Builder $query) use ($id) {
                        $query->where('project_id', '=',  $id);
                    })
                    ->count();
                    $closedRiskCountOfDifferentLevels[] = RiskRegister::whereBetween('inherent_score', [$startScore, $endScore])
                    ->when($id, function (Builder $query) use ($id) {
                        $query->where('project_id', '=',  $id);
                    })
                    ->where('status', 'Close')->count();
                } else {
                    $riskCountWithinRiskLevel = RiskRegister::where('residual_score', '>=', $startScore)
                    ->when($id, function (Builder $query) use ($id) {
                        $query->where('project_id', '=',  $id);
                    })
                    ->count();
                    $closedRiskCountOfDifferentLevels[] = RiskRegister::where('inherent_score', '>=', $startScore)
                    ->when($id, function (Builder $query) use ($id) {
                        $query->where('project_id', '=',  $id);
                    })
                    ->where('status', 'Close')->count();
                }
            $riskCountWithinRiskLevels[] = [
                'name' => $riskLevel->name,
                'risk_count' => $riskCountWithinRiskLevel,
                'color' => $riskLevel->color
            ];

            /* risk count in each category within level*/
            $riskCountWithinRiskLevelForCategory = [
                'name' => $riskLevel->name,
                'data' => [],
                'color' => $riskLevel->color
            ];
            /* Creating risks level color array*/
            $riskLevelColors[] = $riskLevel->color;

            /* setting risk level list*/
            $riskLevelsList[] = $riskLevel->name;
        }

        return $riskCountWithinRiskLevels;
    }

    public function GetClosedRiskCount($id){
        $total=RiskRegister::where('project_id', '=',  $id)->count();
        $closed=RiskRegister::where('project_id', '=',  $id)->where('status', 'Close')->count();
        if($total>0){
            $per=round(($closed/$total)*100);
        }
        else{
            $per=0;
        }
        return $per;
    }

    public function riskRegisterHistoryLogOfProject($id)
    {
        $globalSettings = GlobalSetting::first();
        $nowDateTime = new \DateTime('now', new \DateTimeZone($globalSettings->timezone));
        $todayDate = $nowDateTime->format('Y-m-d');

        $allRiskRegisters = RiskRegister::withTrashed()->select('id','project_id','category_id','status','likelihood','impact','inherent_score','residual_score','is_complete', 'created_at')->where('project_id', $id)->get();
        
        foreach($allRiskRegisters as $risk)
        {
            $riskChangeLog = RiskRegisterHistoryLog::where('log_date', $todayDate)->where('risk_register_id', $risk->id)->first();
            $changeLogData = [
                'risk_deleted_date' => $todayDate
            ];

            if (!is_null($riskChangeLog)) {
                $riskChangeLog->update($changeLogData);
            } else {
                $changeLogData['project_id'] = $risk->project_id;
                $changeLogData['risk_register_id'] = $risk->id;
                $changeLogData['category_id'] = $risk->category_id;
                $changeLogData['log_date'] = $todayDate;
                $changeLogData['risk_created_date'] = $risk->created_at;
                $changeLogData['status'] = $risk->status;
                $changeLogData['likelihood'] = $risk->likelihood;
                $changeLogData['impact'] = $risk->impact;
                $changeLogData['inherent_score'] = $risk->inherent_score;
                $changeLogData['residual_score'] = $risk->residual_score;
                $changeLogData['is_complete'] = is_null($risk->is_complete) ? '0': $risk->is_complete;
                RiskRegisterHistoryLog::create($changeLogData);
            }
        }
    }
}
