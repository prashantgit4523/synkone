<?php

namespace App\Http\Controllers\ThirdPartyRisk;

use App\Traits\HasSorting;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Utils\RegularFunctions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\ThirdPartyRisk\Project;
use App\Models\ThirdPartyRisk\VendorHistoryLog;
use App\Models\ThirdPartyRisk\Project\ProjectVendor;

class DashboardController extends Controller
{
    use HasSorting;

    private string $logs1_score = 'logs1.score';
    private string $logs1VendorDeletedDate = 'logs1.vendor_deleted_date';

    public function __construct() {
        $this->middleware('data_scope')->except('index');
    }

    public function index(){
        $firstProjectDate = Project::withTrashed()->orderBy('created_at', 'asc')->pluck('created_at')->first();
        $today = RegularFunctions::getTodayDate();
        return Inertia::render('third-party-risk/dashboard/Index', compact('firstProjectDate', 'today'));
    }

    public function getVendorsData (Request $request)
    {
        $data = $this->getVendorsMaturity($request);
        $levels = $data['levels'];
        $projects_progress = $data['projects_progress'];

        return response()->json([
            "vendors" => $data['vendors'],
            "levels" => $levels,
            "projects_progress" => $projects_progress,
        ]);
    }

    public function getVendorsMaturity($request)
    {
        $isTodayDate = false;
        $todayDate = RegularFunctions::getTodayDate();
        if(isset($request->date_to_filter)) {
            $dataFilterDate = date("Y-m-d", strtotime($request->date_to_filter));
            $isTodayDate = $dataFilterDate === $todayDate;
        }
        else {
            $dataFilterDate = RegularFunctions::getTodayDate();
            $isTodayDate = true;
        }

        $vendorLog = VendorHistoryLog::from('third_party_vendor_history_logs as logs1')
        ->select('logs1.third_party_vendor_id as log_third_party_vendor_id', 'logs1.status',$this->logs1_score, 'logs1.log_date', $this->logs1VendorDeletedDate , 'logs1.third_party_vendor_id')
        ->where('log_date', function ($query) use($dataFilterDate){
            $query->selectRaw('MAX(logs2.log_date)')
                ->from('third_party_vendor_history_logs as logs2')->whereRaw('logs1.third_party_vendor_id = logs2.third_party_vendor_id');
            
            $query->where('log_date', '<=', $dataFilterDate);
        })
        ->where(function($q) use($dataFilterDate) {
            $q->where($this->logs1VendorDeletedDate, '>', $dataFilterDate)
              ->orWhereNull($this->logs1VendorDeletedDate);
        })
        ->where(function($q) {
            $q->where($this->logs1_score, '>', '0');
        });
        
        $vendorsData = ProjectVendor::rightJoinSub($vendorLog, 'vendor_log', function ($join) {
            $join->on('third_party_project_vendors.vendor_id', '=', 'vendor_log.log_third_party_vendor_id');
        })
        ->select('third_party_project_vendors.name', 'third_party_project_vendors.contact_name', 'vendor_log.log_date', 'vendor_log.log_third_party_vendor_id as third_party_vendor_id', 'vendor_log.score')
        ->distinct('vendor_log.third_party_vendor_id')
        ->get();
        
        if($isTodayDate){
            $vendors = $vendorsData->map(function($row){
                $count = DB::table('third_party_vendors')->where('id',$row->third_party_vendor_id)->count();
    
                if($count){
                    return $row;
                }
            });
        }else{
            $vendors = $vendorsData;
        }

        $levels = [
            [
                'level' => 1,
                'color' => '#ff0000',
                'name' => 'Level 1',
                'count' => $vendors->where('level', 1)->count()
            ],
            [
                'level' => 2,
                'color' => '#ffc000',
                'name' => 'Level 2',
                'count' => $vendors->where('level', 2)->count()
            ],
            [
                'level' => 3,
                'color' => '#ffff00',
                'name' => 'Level 3',
                'count' => $vendors->where('level', 3)->count()
            ],
            [
                'level' => 4,
                'color' => '#92d050',
                'name' => 'Level 4',
                'count' => $vendors->where('level', 4)->count()
            ],
            [
                'level' => 5,
                'color' => '#00b050',
                'name' => 'Level 5',
                'count' => $vendors->where('level', 5)->count()
            ],
        ];

        $projects = Project::withTrashed()->whereDate('created_at', '<=', $dataFilterDate)
        ->select('third_party_projects.id', 'third_party_projects.vendor_id', 'third_party_projects.status', 'third_party_projects.timezone', 'third_party_projects.launch_date', 'third_party_projects.due_date', 'third_party_projects.completed_date', 'third_party_projects.deleted_at')
        ->with('projectVendor', function ($q) use($dataFilterDate) {
            $q->select('third_party_project_vendors.id', 'third_party_project_vendors.vendor_id');
            $q->with('vendor', function ($qry) use($dataFilterDate) {
                $tempFilterDate = date("Y-m-d 23:59:59", strtotime($dataFilterDate));   // this is done to get null value also for vendor deleted on filtered date.
                $qry->withTrashed()->select('third_party_vendors.id', 'third_party_vendors.score', 'third_party_vendors.deleted_at');
                $qry->where('third_party_vendors.deleted_at', '>', $tempFilterDate)
                    ->orWhereNull('third_party_vendors.deleted_at');
            });
        })
        ->get();

        $filterdProjects = $projects->map(function ($row) use($dataFilterDate) {
            if((is_null($row->deleted_at) || $dataFilterDate < date('Y-m-d', strtotime($row->deleted_at))) && !empty($row->projectVendor))
            {
                return $row;
            }
        })
        ->filter(function ($value) {
            return !is_null($value);
        });
        
        $notStarted = 0;
        $inProgress = 0;
        $completed = 0;
        $overdue = 0;

        foreach($filterdProjects as $fp)
        {
            if(!is_null($fp->completed_date) && ($dataFilterDate >= $fp->completed_date)) {
                $completed++;
            }
            else {
                $launchDate = \Carbon\Carbon::parse(date('Y-m-d', strtotime($fp->launch_date)));
                $dueDate = \Carbon\Carbon::parse(date('Y-m-d', strtotime($fp->due_date)));
                $filterDate = \Carbon\Carbon::parse($dataFilterDate);
                if ($filterDate->betweenIncluded($launchDate, $dueDate)) {
                    $inProgress++;
                } else if ($filterDate->lessThan($launchDate)) {
                    $notStarted++;
                } else {
                    $overdue++;
                }
            }
        }

        $projects_progress = [
            "Not Started" => $notStarted,
            "In Progress" => $inProgress,
            "Completed" => $completed,
            "Overdue" => $overdue,
        ];

        $projects_progress_pdf = [
            [
                'level' => 'Not Started',
                'color' => 'rgb(65, 65, 65)',
                'name' => 'Not Started',
                'count' => $notStarted
            ],
            [
                'level' => "In Progress",
                'color' => 'rgb(91, 192, 222)',
                'name' => "In Progress",
                'count' => $inProgress
            ],
            [
                'level' =>  "Completed",
                'color' => 'rgb(53, 159, 29)',
                'name' =>  "Completed",
                'count' => $completed
            ],
            [
                'level' => "Overdue",
                'color' => 'rgb(207, 17, 16)',
                'name' => "Overdue",
                'count' => $overdue
            ]
        ];

        return [
            'vendors' => $vendors,
            'levels' => $levels,
            'projects_progress' => $projects_progress,
            'projects_progress_pdf'=>$projects_progress_pdf
        ];
    }

    public function getTopVendors(Request $request)
    {
        $data = $this->topVendors($request);

        return response()->json(['data' => $data]);
    }

    public function topVendors($request, $paginate = true){
        $isTodayDate = false;
        $todayDate = RegularFunctions::getTodayDate();
        if(isset($request->date_to_filter)) {
            $dataFilterDate = date("Y-m-d", strtotime($request->date_to_filter));
            $isTodayDate = $dataFilterDate === $todayDate;
        }
        else {
            $dataFilterDate = RegularFunctions::getTodayDate();
            $isTodayDate = true;
        }

        $vendorLog = VendorHistoryLog::from('third_party_vendor_history_logs as logs1')
        ->select('logs1.third_party_vendor_id as log_third_party_vendor_id', $this->logs1_score, 'logs1.log_date', $this->logs1VendorDeletedDate)
        ->where('log_date', function ($query) use($dataFilterDate){
            $query->selectRaw('MAX(logs2.log_date)')
                ->from('third_party_vendor_history_logs as logs2')->whereRaw('logs1.third_party_vendor_id = logs2.third_party_vendor_id');
                
            $query->where('log_date', '<=', $dataFilterDate);
        })
        ->where(function($q) use($dataFilterDate, $isTodayDate) {
            if($isTodayDate){
                $q->whereNull($this->logs1VendorDeletedDate);
            }else{
                $q->where($this->logs1VendorDeletedDate, '>', $dataFilterDate)
                ->orWhereNull($this->logs1VendorDeletedDate);
            }
        })
        ->where(function($q) {
            $q->where($this->logs1_score, '>', '0');
        });
        
        $temp_query = ProjectVendor::select('id', 'vendor_id')->whereHas("projectWithTrashed",function($q) use($dataFilterDate){
            $tempFilterDate = date("Y-m-d 23:59:59", strtotime($dataFilterDate));
            $q->where('created_at', '<=', $tempFilterDate);
        })->get();

        $vendorIds = [];
        $projectVendorIds = [];
        foreach($temp_query as $d)
        {
            if(!in_array($d->vendor_id, $vendorIds))
            {
                array_push($vendorIds, $d->vendor_id);
                array_push($projectVendorIds, $d->id);
            }
        }
        
        $vendors_query = ProjectVendor::query()->whereIn("third_party_project_vendors.id", $projectVendorIds)
        ->rightJoinSub($vendorLog, 'vendor_log', function ($join) {
            $join->on('third_party_project_vendors.vendor_id', '=', 'vendor_log.log_third_party_vendor_id');
        })
        ->with('vendorWithTrashed:id,status')
        ->select('third_party_project_vendors.id', 'third_party_project_vendors.project_id', 'third_party_project_vendors.vendor_id', 'third_party_project_vendors.name', 'third_party_project_vendors.contact_name', 'vendor_log.log_date', 'vendor_log.log_third_party_vendor_id as third_party_vendor_id', 'vendor_log.score', 'third_party_project_vendors.status');
        
        $per_page = 10;

        if ($request->has('search')) {
            $vendors_query->where('name', 'LIKE', '%' . $request->search . '%');
        }

        if ($request->has('per_page')) {
            $per_page = $request->per_page;
        }

        $this->sort(['name', 'contact_name', 'status', 'score'], $vendors_query);

        if($paginate){
            $data = $vendors_query->orderByDesc('score')->paginate($per_page);
        } else {
            $data = $vendors_query->orderByDesc('score')->limit($per_page)->get();
        }
        
        // To get the latest project of the vendor
        $allProjectVendor = ProjectVendor::whereIn('vendor_id', $data->pluck('vendor_id')->toArray())->orderBy('id','DESC')->with('project:id')->get();
        $vendorArray = $data->pluck('vendor_id')->toArray();
        $projectVendorList = collect();
        foreach($vendorArray as $arr){
            $projectVendorList->push($allProjectVendor->where('vendor_id',$arr)->first());
        }
        
        foreach($data as $val)
        {
            foreach($projectVendorList as $val2)
            {
                if($val['vendor_id'] == $val2['vendor_id'])
                {
                    $val->latest_project = $val2['project'];
                    break;
                }
            }
        }
        
        return $data;
    }

    public function exportPDF(Request $request){
        $vendors_maturity_data = $this->getVendorsMaturity($request);
        $top_vendors = $this->topVendors($request, false);

        $data = $vendors_maturity_data;
        $data["top_vendors"] = $top_vendors;

        $pdf = \PDF::loadView('third-party-risks.dashboard-pdf-report', $data);
        $pdf->setOptions([
            'enable-local-file-access' => true,
            'enable-javascript' => true,
            'javascript-delay' => 3000,
            'enable-smart-shrinking' =>  true,
            'no-stop-slow-scripts' => true,
            'header-center' => 'Note: This is a system generated report',
            'footer-center' => 'Third Party Risk Report',
            'footer-left' => 'Confidential',
            'footer-right' => '[page]',
            'debug-javascript' => true,
        ]);

        Log::info('User has downloaded a third party risk dashboard report as pdf.');
        return $pdf->inline('third-party-risk.pdf');
    }
}
