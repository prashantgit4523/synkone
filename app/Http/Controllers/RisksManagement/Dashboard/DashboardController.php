<?php

namespace App\Http\Controllers\RisksManagement\Dashboard;

use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Models\DataScope\DataScope;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\AssetManagement\Asset;
use Illuminate\Database\Eloquent\Builder;
use App\Models\RiskManagement\RiskCategory;
use App\Models\RiskManagement\RiskRegister;
use App\Models\RiskManagement\Project;
use App\Models\RiskManagement\RiskRegisterHistoryLog;
use App\Models\RiskManagement\RiskMatrix\RiskScoreLevel;
use App\Models\RiskManagement\RiskMatrix\RiskMatrixScore;
use App\Models\RiskManagement\RiskMatrix\RiskMatrixImpact;
use App\Models\RiskManagement\RiskMatrix\RiskScoreLevelType;
use App\Models\RiskManagement\RiskMatrix\RiskMatrixLikelihood;
use App\Models\GlobalSettings\GlobalSetting;

class DashboardController extends Controller
{
    private $baseViewPath = 'risk-management.dashboard.';
    private $residualScore = 'risk_register_log.residual_score';
    private $riskRegisterLogStatus = 'risk_register_log.status';
    private $categoryId = 'risk_register_log.category_id';
    private $riskDeletedDate = 'logs1.risk_deleted_date';

    public function index()
    {
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
        $firstRiskDate = RiskRegister::withTrashed()->orderBy('created_at', 'asc')->pluck('created_at')->first();
        $firstRiskDate = date('Y-m-d', strtotime($firstRiskDate));
        $today = date('Y-m-d');
        return Inertia::render('risk-management/dashboard/Dashboard',
            [
                'risksAffectedProperties' => $risksAffectedProperties,
                'riskMatrixImpacts' => $riskMatrixImpacts,
                'riskMatrixLikelihoods' => $riskMatrixLikelihoods,
                'riskMatrixScores' => $riskMatrixScores,
                'riskScoreActiveLevelType' => $riskScoreActiveLevelType,
                'riskLikelihoods' => $riskLikelihoods,
                'riskImpacts' => $riskImpacts,
                'riskCategories' =>  $riskCategories,   
                'firstRiskDate' =>  $firstRiskDate,
                'today' =>  $today,
                'assets' => Asset::select('name')->get()->map(function ($asset) {
                    return [
                      'label' => $asset->name,
                      'value' => $asset->name
                    ];
                }),
            ]
        );
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
                    $delProj = Project::onlyTrashed()->whereDate('deleted_at', '>', $dataFilterDate)->withoutGlobalScope(DataScope::class)->pluck('id');
                    $mergedProjects = array_merge($request->projects, $delProj->toArray());
                    $query->whereIn('project_id', $mergedProjects);
                }

                if(isset($request->project_id) && $request->project_id != null) {
                    if(!is_array($request->project_id)){
                        $query->where('project_id', $request->project_id);
                    }else{
                        // To include the deleted project ids also
                        $delProj = Project::onlyTrashed()->whereDate('deleted_at', '>', $dataFilterDate)->withoutGlobalScope(DataScope::class)->pluck('id');
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
            return RiskRegister::withTrashed()->withoutGlobalScope(DataScope::class)->joinSub($riskRegisterLog, 'risk_register_log', function ($join) {
                $join->on('risks_register.id', '=', 'risk_register_log.log_risk_register_id');
            });
        }
        else
        {
            return RiskRegister::withTrashed()->joinSub($riskRegisterLog, 'risk_register_log', function ($join) {
                $join->on('risks_register.id', '=', 'risk_register_log.log_risk_register_id');
            });
        }
    }

    public function getDashboardDataJson(Request $request)
    {
        $data= $this->getDashboardData($request);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    private function getDashboardData($request)
    {
        if (isset($request->departments)) {
            $topTenRisks = [];
            $riskRegisterCategories = RiskCategory::whereHas('riskRegisterWithoutScope',function($query) use ($request){
                if($request->projects){
                    $query->whereIn('project_id', $request->projects);
                }
                else{
                    $query->where('project_id','=',  $request->project_id);
                }
                if($request->filterDate){
                    $query->whereDate('created_at', '<=', $request->filterDate);
                }
            })->get();
        } else {
            $topTenRisks = [];
                                
            $riskRegisterCategories = RiskCategory::whereHas('riskRegister',function($query) use ($request){
                if($request->projects){
                    $query->whereIn('project_id', $request->projects);
                }
                else{
                    $query->where('project_id','=',  $request->project_id);
                }
                if($request->filterDate){
                    $query->whereDate('created_at', '<=', $request->filterDate);
                }
            })->get();
        }

        $riskLevels = RiskScoreLevel::whereHas('levelTypes', function ($query) {
            $query->where('is_active', 1);
        })->get();

        $riskRegisterCategoriesList = $riskRegisterCategories->pluck('name')->toArray();
        $riskCountWithinRiskLevelForCategories = [];
        $riskCountWithinRiskLevels = [];
        $riskLevelColors = [];
        $riskLevelsList = [];
        $closedRiskCountOfDifferentLevels = [];

        foreach ($riskLevels as $riskLevelIndex => $riskLevel) {
            $startScore =  $riskLevelIndex == 0 ? 0 : $riskLevels[$riskLevelIndex-1]->max_score + 1;
            $endScore = $riskLevel->max_score;
            $isLastRiskLevelIndex = $riskLevels->keys()->last() == $riskLevelIndex;

            if (isset($request->departments)) {
                /* Creating risk count within different levels */
                /* closed Risk Of DifferentLevels */
                if (!$isLastRiskLevelIndex) {
                    $riskCountWithinRiskLevel = $this->getDashboardDataFromHistory($request, true)
                                                     ->ofDepartment()
                                                     ->whereBetween($this->residualScore, [$startScore, $endScore])->count();
                                                     
                    $closedRiskCountOfDifferentLevels[] = $this->getDashboardDataFromHistory($request, true)
                                                                ->ofDepartment()
                                                                ->whereBetween($this->residualScore, [$startScore, $endScore])
                                                                ->where($this->riskRegisterLogStatus, 'Close')->count();
                } else {
                    $riskCountWithinRiskLevel = $this->getDashboardDataFromHistory($request, true)
                                                        ->ofDepartment()
                                                        ->where($this->residualScore, '>=', $startScore)->count();
                                                        
                    $closedRiskCountOfDifferentLevels[] = $this->getDashboardDataFromHistory($request, true)
                                                                ->ofDepartment()
                                                                ->where($this->residualScore, '>=', $startScore)
                                                                ->where($this->riskRegisterLogStatus, 'Close')->count();
                }
            } else {
                /* Creating risk count within different levels */
                /* closed Risk Of DifferentLevels */
                if (!$isLastRiskLevelIndex) {
                    $riskCountWithinRiskLevel = $this->getDashboardDataFromHistory($request)
                                                    ->whereBetween($this->residualScore, [$startScore, $endScore])->count();

                    $closedRiskCountOfDifferentLevels[] = $this->getDashboardDataFromHistory($request)
                                                                ->whereBetween($this->residualScore, [$startScore, $endScore])
                                                                ->where($this->riskRegisterLogStatus, 'Close')->count();
                } else {
                    $riskCountWithinRiskLevel = $this->getDashboardDataFromHistory($request)
                                                        ->where($this->residualScore, '>=', $startScore)->count();

                    $closedRiskCountOfDifferentLevels[] = $this->getDashboardDataFromHistory($request)
                                                                ->where($this->residualScore, '>=', $startScore)
                                                                ->where($this->riskRegisterLogStatus, 'Close')->count();
                }
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

            foreach ($riskRegisterCategories as $riskRegisterCategory) {
                if (isset($request->departments)) {
                    if (!$isLastRiskLevelIndex) {
                        $riskCountWithinRiskLevelOfCategory = $this->getDashboardDataFromHistory($request, true)
                                                                    ->ofDepartment()
                                                                    ->where($this->categoryId, $riskRegisterCategory->id)
                                                                    ->whereBetween($this->residualScore, [$startScore, $endScore])->count();
                    } else {
                        $riskCountWithinRiskLevelOfCategory = $this->getDashboardDataFromHistory($request, true)
                                                                    ->ofDepartment()
                                                                    ->where($this->categoryId, $riskRegisterCategory->id)
                                                                    ->where($this->residualScore, '>=', $startScore)->count();
                    }
                } else {
                    if (!$isLastRiskLevelIndex) {
                        $riskCountWithinRiskLevelOfCategory = $this->getDashboardDataFromHistory($request)
                                                                    ->where($this->categoryId, $riskRegisterCategory->id)
                                                                    ->whereBetween($this->residualScore, [$startScore, $endScore])->count();
                    } else {
                        $riskCountWithinRiskLevelOfCategory = $this->getDashboardDataFromHistory($request)
                                                                    ->where($this->categoryId, $riskRegisterCategory->id)
                                                                    ->where($this->residualScore, '>=', $startScore)->count();
                    }
                }

                $riskCountWithinRiskLevelForCategory['data'][] = $riskCountWithinRiskLevelOfCategory;
            }
            
            $riskCountWithinRiskLevelForCategories[] = $riskCountWithinRiskLevelForCategory;


            /* Creating risks level color array*/
            $riskLevelColors[] = $riskLevel->color;

            /* setting risk level list*/
            $riskLevelsList[] = $riskLevel->name;
        }

        return [
            'topTenRisks' => $topTenRisks,
            'riskRegisterCategoriesList' => $riskRegisterCategoriesList,
            'riskCountWithinRiskLevelForCategories' => $riskCountWithinRiskLevelForCategories,
            'riskCountWithinRiskLevels' => $riskCountWithinRiskLevels,
            'riskLevelColors' => $riskLevelColors,
            'riskLevelsList' => $riskLevelsList,
            'closedRiskCountOfDifferentLevels' => $closedRiskCountOfDifferentLevels
        ];
    }

    public function getTopRisksJson(Request $request)
    {
        $page = $request->page ?? 1;
        $size = $request->per_page ?? 10;
        $keyword = $request->search ?? null;
        $start = ($page - 1) * $size;

        if (isset($request->departments)) {
            $risk_register = RiskRegister::with('project')->withoutGlobalScope(DataScope::class)->ofDepartment()
                ->orderBy('residual_score', 'DESC')
                ->when($request->projects, function (Builder $query) use ($request) {
                    $query->whereIn('project_id', $request->projects);
                })
                ->when($keyword, function ($query) use ($keyword) {
                    return $query->where('name', 'LIKE', $keyword . '%');
                });
        } else {
            $risk_register = RiskRegister::with('project')
            ->orderBy('residual_score', 'DESC')
            ->when($request->projects, function (Builder $query) use ($request) {
                $query->whereIn('project_id', $request->projects);
            })
            ->when($keyword, function ($query) use ($keyword) {
                return $query->where('name', 'LIKE', $keyword . '%');
            });
        }

        $count = $risk_register->count();
        $risk_register = $risk_register->with('category')->skip(--$page * $size)->take($size)->paginate($size);
        $risk_register->getCollection()->transform(function ($risk, $key) use ($start) {
            // add an index to each risk
            $risk['index'] = $key + $start + 1;
            return $risk;
        });
        return response()->json([
            'data' => $risk_register,
            'total' => $count,
        ], 200);
    }

    public function getTopRisks(Request $request)
    {
        $start = $request->start;
        $length = $request->length;
        $draw = $request->draw;
        $count = 0;
        $render = [];

        $riskStatusClass = [
            'Mitigate' => 'bg-danger',
            'Accept' => 'bg-success',
            'Closed' => 'bg-warning'
        ];

        $sortColumns = $request->order;

        $topRiskQuery = RiskRegister::with('category');
        $count = $topRiskQuery->count();

        // for first time draw
        if ($draw == 1) {
            $topRiskQuery->orderBy('residual_score', 'DESC');
        }

        // sort by residual risk score
        if ($sortColumns[0]['column'] == 8) {
            $topRiskQuery->orderBy('residual_score', $sortColumns[0]['dir']);
        }

        // sort by inherent risk score
        if ($sortColumns[0]['column'] == 7) {
            $topRiskQuery->orderBy('inherent_score', $sortColumns[0]['dir']);
        }

        // sort by treatment_options
        if ($sortColumns[0]['column'] == 4) {
            $topRiskQuery->orderBy('treatment_options', $sortColumns[0]['dir']);
        }

        // sort by id
        if ($sortColumns[0]['column'] == 0) {
            $topRiskQuery->orderBy('id', $sortColumns[0]['dir']);
        }

        // sort by name or title
        if ($sortColumns[0]['column'] == 1) {
            $topRiskQuery->orderBy('name', $sortColumns[0]['dir']);
        }

        $topRisks = $topRiskQuery->offset($start)->take($length)->get();


        // sorting on collection after query, suited for computed attributes
        // sort by category name
        if ($sortColumns[0]['column'] == 2) {
            if ($sortColumns[0]['dir'] == "asc") {
                $topRisks = $topRisks->sortBy('category.name');
            } else {
                $topRisks = $topRisks->sortByDesc('category.name');
            }
        }

        // sort by likelihood
        if ($sortColumns[0]['column'] == 5) {
            if ($sortColumns[0]['dir'] == "asc") {
                $topRisks = $topRisks->sortBy('likelihood');
            } else {
                $topRisks = $topRisks->sortByDesc('likelihood');
            }
        }

        // sort by impact
        if ($sortColumns[0]['column'] == 6) {
            if ($sortColumns[0]['dir'] == "asc") {
                $topRisks = $topRisks->sortBy('impact');
            } else {
                $topRisks = $topRisks->sortByDesc('impact');
            }
        }

        // sort by status
        if ($sortColumns[0]['column'] == 3) {
            if ($sortColumns[0]['dir'] == "asc") {
                $topRisks = $topRisks->sortBy('status');
            } else {
                $topRisks = $topRisks->sortByDesc('status');
            }
        }



        // building data to be renders in datatable
        $i = $start;
        foreach ($topRisks as $topRisk) {
            $status = '<span class="badge bg-danger rounded-pill">Open</span>';

            if ($topRisk->status == 'Close') {
                $status = '<span class="badge bg-success rounded-pill">Closed</span>';
            }

            $render[] = [
                ++$i,
                $topRisk->name,
                $topRisk->category->name,
                $status,
                '<span class="badge'.$riskStatusClass[$topRisk->treatment_options].' rounded-pill">'.$topRisk->treatment_options.'</span>',
                $topRisk->riskMatrixLikelihood ?  $topRisk->riskMatrixLikelihood->name : '', // computed likelihood
                $topRisk->riskMatrixImpact ? $topRisk->riskMatrixImpact->name : '', // computed impact
                $topRisk->inherent_score, // inherent risk score
                $topRisk->residual_score, // residual scrore
                '<a href="'.route('risks.register.risks-show', $topRisk->id) .'" class="btn btn-primary btn-view btn-sm width-sm">View</a>'
            ];
        }

        $response = array(
            'draw' => $draw,
            'recordsTotal' => $count,
            'recordsFiltered' => $count,
            'data' => $render,
        );

        return response()->json($response);
    }

    public function generatePdfReport(Request $request)
    {
        $data = $this->getDashboardData($request);
        if (isset($request->departments)) {
            $topTenRisks = $this->getDashboardDataFromHistory($request, true)
                                ->ofDepartment()
                                ->with('category')->get();
        } else {
            $topTenRisks = $this->getDashboardDataFromHistory($request, false)
                                ->with('category')->get();
        }
        $data['topTenRisks']=$topTenRisks;
        // review report
        // return view($this->baseViewPath.'pdf-report', $data);
        $pdf = \PDF::loadView('risk-management.dashboard.pdf-report', $data);
        
        $pdf->setOptions([
            'enable-local-file-access' => true,
            'enable-javascript' => true,
            'javascript-delay' => 3000,
            'enable-smart-shrinking' =>  true,
            'no-stop-slow-scripts' => true,
            'header-center' => 'Note: This is a system generated report',
            'footer-center' => 'Risk Report',
            'footer-left' => 'Confidential',
            'footer-right' => '[page]',
            'debug-javascript' => true,
        ]);

        Log::info('User has downloaded a risks report.');

        return $pdf->inline('risks-report.pdf');
    }
}
