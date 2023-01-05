<?php

namespace App\Http\Controllers\RisksManagement\RiskRegister;

use App\Models\DataScope\DataScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use App\Models\Compliance\Standard;
use App\Models\RiskManagement\RiskCategory;
use App\Models\RiskManagement\RiskRegister;
use App\Models\RiskManagement\RiskProject;
use App\Traits\RisksManagement\HelperMethodsTrait;
use App\Models\RiskManagement\RiskMatrix\RiskMatrixScore;
use App\Rules\RiskManagement\ValidRiskAffectedProperties;
use App\Models\RiskManagement\RiskMatrix\RiskMatrixImpact;
use App\Models\RiskManagement\RiskMatrix\RiskScoreLevelType;
use App\Models\RiskManagement\RiskMatrix\RiskMatrixLikelihood;
use App\Models\RiskManagement\RiskRegisterHistoryLog;
use App\Models\GlobalSettings\GlobalSetting;
use Inertia\Inertia;

class RiskRegisterReactController
{
    use HelperMethodsTrait;

    private $riskRegisterName = 'risks_register.name';
    private $riskRegisterId = 'risks_register.id';
    private $riskRegisterTreatmentOptions = 'risks_register.treatment_options';
    private $isComplete = 'risks_register.is_complete';
    private $likelihood = 'risk_register_log.likelihood';
    private $riskDeletedDate = 'logs1.risk_deleted_date';

    public function __construct()
    {
    }

    public function index(Request $request)
    {
        $riskCategories = RiskCategory::query()->withCount(['registerRisks as total_risks' => function (Builder $query) use ($request) {
            $query->where('name', 'LIKE', '%' . $request->search . '%');
            $query->where('project_id',$request->project_id);
            $query->when($request->has('only_incomplete') && $request->only_incomplete === 'true', function (Builder $query) {
               $query->where('is_complete', 0);
            });
        }])->having('total_risks', '>', 0)->withCount(['registerRisks as total_incomplete_risks' => function (Builder $query) use ($request) {
                $query->where('name', 'LIKE', '%' . $request->search . '%');
                $query->where('is_complete', 0);
            }])->get();

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
        
        return response()->json(['success' => true,'data'=>[
        'risksAffectedProperties' => $risksAffectedProperties,
        'riskMatrixImpacts' => $riskMatrixImpacts,
        'riskMatrixLikelihoods' => $riskMatrixLikelihoods,
        'riskMatrixScores' => $riskMatrixScores,
        'riskScoreActiveLevelType' => $riskScoreActiveLevelType,
        'riskCategories' =>  $riskCategories,   
        'data'=>$riskCategories
        ]]);
    }

    public function riskUpdate(Request $request, $Id)
    {
        $inputValidationRules = [
            'affected_properties' => [
                'required',
                'max:150',
                new ValidRiskAffectedProperties(),
            ],
            'treatment_options' => 'required|in:Mitigate,Accept',
            'likelihood' => 'required',
            'impact' => 'required',
        ];

        $riskInputs = [];

        $input = $request->toArray();

        if ($request->has('affected_functions_or_assets')) {
            $riskInputs['affected_functions_or_assets'] = $input['affected_functions_or_assets'];

            // validation rule
            $inputValidationRules['affected_functions_or_assets'] = 'required|max:150';
        }

        if ($request->treatment_options) {
            $riskInputs['treatment_options'] = $input['treatment_options'];
        }

        $request->validate($inputValidationRules);

        $risk = RiskRegister::find($Id);

        $affectedProperties = $input['affected_properties'];

        $riskScore = RiskMatrixScore::where('likelihood_index', $input['likelihood'])->where('impact_index', $input['impact'])->first();


        $riskInputs = array_merge($riskInputs, [
            'affected_properties' => $affectedProperties,
            'treatment_options' => $input['treatment_options'],
            'likelihood' => $input['likelihood'] + 1,
            'impact' => $input['impact'] +1,
            'inherent_score' => $riskScore->score,
            'residual_score' => $riskScore->score,
            'is_complete' => 1,
        ]);

        $risk->update($riskInputs);

        return redirect()->back();

    }

    public function riskShow($id)
    {
        /* Getting compliance standards */
        $data['allComplianceStandards'] = Standard::whereHas('projects')->get();

        $data['risk'] = RiskRegister::with(['category','owner','custodian'])->findOrFail($id);
        return response()->json(['success' => true,'data'=>$data]);
    }

    /**
     * get standard filter options for map controls risk register show
     */
    public function getFilterOptions(Request $request)
    {
        $data=[];
        $allStandards = Standard::whereHas('projects')->get();
        //manage data for dropdown
        $managedStandards[] = [
            'value' => 0,
            'label' => "Select Standard"
        ];
        foreach ($allStandards as $key => $eachStandard) {
            $managedStandards[] = ['value'=>$eachStandard['id'],'label'=>$eachStandard['name']];
        }
        $data['managedStandards']=$managedStandards;
        $data['projects']=[];
        if($request->standardId){
            $projects = [];
            $standardId = $request->standardId;
            $standard = Standard::find($standardId);

            if ($standard) {
                $managedProjects[] = [
                    'value' => 0,
                    'label' => "Select Project"
                ];
                $projects = $standard->projects()->get();
                foreach ($projects as $key => $eachProject) {
                    $managedProjects[] = ['value'=>$eachProject['id'],'label'=>$eachProject['name']];
                }
                $data['projects']=$managedProjects;
            }
        }

        return response()->json(['data'=>$data]);
    }

    private function getDashboardDataFromHistory($request, $withoutGlobalScope=false)
    {
        if(isset($request->filterDate)) {
            $dataFilterDate = date("Y-m-d", strtotime($request->filterDate));
        }
        else if(isset($request->dateToFilter)) {
            $dataFilterDate = date("Y-m-d", strtotime($request->dateToFilter));
        }
        else {
            $globalSettings = GlobalSetting::first();
            $nowDateTime = new \DateTime('now', new \DateTimeZone($globalSettings->timezone));
            $dataFilterDate = $nowDateTime->format('Y-m-d');
        }

        $riskRegisterLog = RiskRegisterHistoryLog::from('risk_register_history_log as logs1')
        ->select('logs1.risk_register_id as log_risk_register_id', 'logs1.project_id', 'logs1.category_id', 'logs1.log_date', 'logs1.status', 'logs1.likelihood', 'logs1.impact', 'logs1.inherent_score', 'logs1.residual_score', 'logs1.is_complete', $this->riskDeletedDate)
        ->where('log_date', function ($query) use($request, $dataFilterDate){
            $query->selectRaw('MAX(logs2.log_date)')
                ->from('risk_register_history_log as logs2')->whereRaw('logs1.risk_register_id = logs2.risk_register_id')->where('log_date', '<=', $dataFilterDate);
                
                if(isset($request->projects)) {
                    // To include the deleted project ids also
                    $delProj = \DB::table('risk_projects')->whereDate('deleted_at', '>', $dataFilterDate)->pluck('id');
                    $mergedProjects = array_merge($request->projects, $delProj->toArray());
                    $query->whereIn('project_id', $mergedProjects);
                }

                if(isset($request->project_id) && $request->project_id != null) {
                    if(!is_array($request->project_id)){
                        $query->where('project_id', $request->project_id);
                    }else{
                        // To include the deleted project ids also
                        $delProj = \DB::table('risk_projects')->whereDate('deleted_at', '>', $dataFilterDate)->pluck('id');
                        $mergedProjects = array_merge($request->project_id, $delProj->toArray());
                        $query->whereIn('project_id', $mergedProjects);
                    }
                }
        })
        ->where(function($q) use($dataFilterDate) {
            $q->where($this->riskDeletedDate, '>', $dataFilterDate)
              ->orWhereNull($this->riskDeletedDate);
        });

        if($withoutGlobalScope)
        {
            return RiskRegister::withTrashed()->select($this->riskRegisterId, $this->riskRegisterName, $this->isComplete, 'risks_register.primary_id', 'risks_register.sub_id', $this->riskRegisterTreatmentOptions, 'risks_register.risk_description', 'risks_register.affected_properties', 'risks_register.affected_functions_or_assets', 'risk_register_log.*')->withoutGlobalScope(DataScope::class)->rightJoinSub($riskRegisterLog, 'risk_register_log', function ($join) {
                $join->on($this->riskRegisterId, '=', 'risk_register_log.log_risk_register_id');
            });
        }
        else
        {
            return RiskRegister::withTrashed()->select($this->riskRegisterId, $this->riskRegisterName, $this->isComplete, 'risks_register.primary_id', 'risks_register.sub_id', $this->riskRegisterTreatmentOptions, 'risks_register.risk_description', 'risks_register.affected_properties', 'risks_register.affected_functions_or_assets', 'risk_register_log.*')->rightJoinSub($riskRegisterLog, 'risk_register_log', function ($join) {
                $join->on($this->riskRegisterId, '=', 'risk_register_log.log_risk_register_id');
            });
        }
    }

    public function registeredRisks($id, Request $request){
        $searchRequestType = array("open","closed","accept","mitigate");     
        $defaultPerPage = 10;
        $order = ($request->filterOrder == "ASC")?"ASC":"DESC";
        if($request->perPage > 0){
            $defaultPerPage = $request->perPage;
        }
        $risks = $this
            ->getDashboardDataFromHistory($request, true)
            ->with(['project' => function ($q) {
                $q->withoutGlobalScopes();
            }])
            ->leftJoin('risks_categories as rc','risk_register_log.category_id','rc.id')
            ->leftJoin('risks_mapped_compliance_controls as rmc',$this->riskRegisterId,'rmc.risk_id')
            ->leftJoin('compliance_project_controls as cpc','rmc.control_id','cpc.id')
            ->when($request->only_incomplete === 'true', function (Builder $query) {
                $query->where($this->isComplete, '=',  0);
            })
            ->with(['mappedControls','riskMatrixLikelihood', 'riskMatrixImpact','category']);
        
            /// search for control mapped
            if(strpos($request->search, '.') !== false || strpos($request->search, '-') !== false || strpos($request->search, ',') !== false){
                $project_control_filter='';
                $id_seperator='';
                switch($request->search){
                    case strpos($request->search, '.') !== false:
                        $id_seperator='.';
                        $project_control_filter=explode('.',$request->search,2);
                        break;
                    case strpos($request->search, '-') !== false:
                        $project_control_filter=explode('.',$request->search,2);
                        $id_seperator='-';
                        break;
                    case strpos($request->search, ',') !== false:
                        $project_control_filter=explode('.',$request->search,2);
                        $id_seperator=',';
                        break;
                }
                if(count($project_control_filter)>0){
                    $risks->where('cpc.id_separator',$id_seperator);
                    $risks->where('cpc.primary_id',$project_control_filter[0]);
                    if(!empty($project_control_filter[1])){
                        $risks->where('cpc.sub_id','like',$project_control_filter[1].'%');
                    }
                }
            }
            else{
                if(is_numeric($request->search)){
                    $risks->where($this->likelihood,$request->search)->orWhere([$this->likelihood=>$request->search,"risk_register_log.impact"=>$request->search,"risk_register_log.inherent_score"=>$request->search,"risk_register_log.residual_score"=>$request->search]);
                }else if(array_intersect($searchRequestType,[strtolower($request->search)])){
                    if(strtolower($request->search) == "open" || strtolower($request->search) == "closed"){
                        $risks->where("risks_register.status",'like',strtolower($request->search) == "open"?"Open":"Close");
                    }else{
                        $risks->where("risks_register.treatment_options",'like',$request->search);
                    }
                }else{
                    $risks->where($this->riskRegisterName, 'LIKE', '%'. $request->search .'%');
                } 
            }

            
            if(($request->filterName) && ($request->filterOrder)){
                $targetColumn = "";
                switch ($request->filterName) {
                    case 'id':
                        $targetColumn = $this->riskRegisterId;
                        break;
                    case 'title':
                        $targetColumn = "risks_register.name";
                        break;
                    case 'category':
                        $targetColumn = "";
                        $risks->orderBy('rc.name',$order);
                        break;
                    case 'control':
                        $targetColumn = "rc.name";
                        break;
                    case 'status':
                        $targetColumn = "risk_register_log.status";
                        break;
                    case 'likelihood':
                        $targetColumn = $this->likelihood;
                        break;
                    case 'inherentScore':
                        $targetColumn = "risk_register_log.inherent_score";
                        break;
                    case 'residualScore':
                        $targetColumn = "risk_register_log.residual_score";
                        break;
                    default:
                        $targetColumn = $this->riskRegisterId;
                        break;
                }
                if($targetColumn != ""){
                    $risks = $risks->orderBy($targetColumn,$order);
                }
            }
        $finalRisks = $risks->paginate($defaultPerPage);
        return response()->json($finalRisks);
    }
}
