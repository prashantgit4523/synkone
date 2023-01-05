<?php

namespace App\Http\Controllers\Globals;

use App\Http\Controllers\Controller;
use App\Models\Administration\OrganizationManagement\Department;
use App\Models\Administration\OrganizationManagement\Organization;
use App\Models\Compliance\Project;
use App\Models\Compliance\ProjectControl;
use Auth;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use App\Models\DataScope\DataScope;
use App\Models\DataScope\Scopable;
use Inertia\Inertia;
use App\Models\UserManagement\Admin;
use App\Rules\ValidDataScope;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Models\Compliance\ComplianceProjectControlHistoryLog;
use App\Utils\RegularFunctions;

class DashboardController extends Controller
{
    protected $loggedUser;

    private const UNDER_REVIEW = 'Under Review';
    private const IMPLEMENTED = 'Implemented';
    private const NOT_IMPLEMENTED = 'Not Implemented';

    private const PROJECT_CONTROL_LOG_STATUS = 'project_control_log.status';
    private const PROJECT_CONTROL_LOG_DEADLINE = 'project_control_log.deadline';
    private const PROJECT_CONTROL_LOG_APPLICABLE = 'project_control_log.applicable';

    private const CONTROL_DELETED_DATE = 'logs1.control_deleted_date';

    public function __construct()
    {
        $this->middleware('auth:admin');
        $this->middleware(function ($request, $next) {
            $this->loggedUser = Auth::guard('admin')->user();

            return $next($request);
        });
    }

    public function showDashboard()
    {
        // Check If The Application Is Updated Or Not if there is update key  in session

        \View::share('page_title', 'Dashboard');
        $firstControlDate = ProjectControl::withTrashed()->orderBy('created_at', 'asc')->pluck('created_at')->first();
        $today = RegularFunctions::getTodayDate();
        return Inertia::render('global-dashboard/GlobalDashboard', ['today'=>$today, 'firstControlDate' => $firstControlDate]);
    }

    private function buildDashboardData($request)
    {
        /* Setting default keys and values for the data */
        $data = [
            'allControls' => 0,
            'applicableControls' => 0,
            'notApplicableControls' => 0,
            'notImplementedControls' => 0,
            'underReviewControls' => 0,
            'implementedControls' => 0,
            'allUpcomingTasks' => 0,
            'allDueTodayTasks' => 0,
            'allPassDueTasks' => 0,
            'completedTasksPercent' => 0
        ];

        /* Handling data scoping data*/
        $dataScopeData = explode('-', $request->data_scope);
        $dataScopeType =  $dataScopeData[1] > 0 ? 'department' : 'organization';
        $dataScopeId = ($dataScopeType == 'department') ? $dataScopeData[1] : $dataScopeData[0];


        /* Includes applicable, non applicable, of all status */
        $allControls = $this->dashboardDataBaseQuery($request)->count();
        
        $data['allControls'] = $allControls;

        /* applicable control of all status*/
        $applicableControls = $this->dashboardDataBaseQuery($request)
                            ->where(self::PROJECT_CONTROL_LOG_APPLICABLE, 1)
                            ->count();
        $data['applicableControls'] = $applicableControls;

        // Querying for under review controls
        $underReviewControls = $this->dashboardDataBaseQuery($request)
                                ->where(self::PROJECT_CONTROL_LOG_STATUS, self::UNDER_REVIEW)
                                ->count();

        $data['underReviewControls'] = $underReviewControls;

        
        // Querying for Implemented controls
        $implementedControls = $this->dashboardDataBaseQuery($request)
                                ->where(self::PROJECT_CONTROL_LOG_STATUS, self::IMPLEMENTED)
                                ->count();

        $data['implementedControls'] = $implementedControls;

        // Quering for not applicable controls
        $notApplicableControls = $this->dashboardDataBaseQuery($request)
                                    ->where(self::PROJECT_CONTROL_LOG_APPLICABLE, 0)
                                    ->count();

        $data['notApplicableControls'] = $notApplicableControls;

        // Querying for not implemented controls
        $notImplementedControl =  $this->dashboardDataBaseQuery($request)
                                    ->where(self::PROJECT_CONTROL_LOG_STATUS, self::NOT_IMPLEMENTED)
                                    ->where(self::PROJECT_CONTROL_LOG_APPLICABLE, 1)
                                    ->count();

        $rejectedControls = $this->dashboardDataBaseQuery($request)
                                ->where(self::PROJECT_CONTROL_LOG_STATUS, 'Rejected')
                                ->count();
        $notImplementedControls = $notImplementedControl + $rejectedControls;

        
        $data['notImplementedControls'] = $notImplementedControls;
        
        $deadlineDate = RegularFunctions::getTodayDate();
        if(isset($request->date)) {
            $deadlineDate = date("Y-m-d", strtotime($request->date));
        }

        // all upcoming task
        $allUpcomingTasks = $this->dashboardDataBaseQuery($request)
            ->where(self::PROJECT_CONTROL_LOG_APPLICABLE, 1)
            ->where(self::PROJECT_CONTROL_LOG_DEADLINE, '>', $deadlineDate)
            ->where(self::PROJECT_CONTROL_LOG_STATUS, '!=', self::IMPLEMENTED)
            ->where(self::PROJECT_CONTROL_LOG_STATUS, '!=', self::UNDER_REVIEW)
            ->count();

        $data['allUpcomingTasks'] = $allUpcomingTasks;

        // all dueToday task
        $allDueTodayTasks = $this->dashboardDataBaseQuery($request)
            ->where(self::PROJECT_CONTROL_LOG_APPLICABLE, 1)
            ->where(self::PROJECT_CONTROL_LOG_DEADLINE, $deadlineDate)
            ->where(self::PROJECT_CONTROL_LOG_STATUS, '!=', self::IMPLEMENTED)
            ->where(self::PROJECT_CONTROL_LOG_STATUS, '!=', self::UNDER_REVIEW)
            ->count();

        $data['allDueTodayTasks'] = $allDueTodayTasks;

        // all_pass_due_tasks
        $allPassDueTasks = $this->dashboardDataBaseQuery($request)
            ->where(self::PROJECT_CONTROL_LOG_APPLICABLE, 1)
            ->where(self::PROJECT_CONTROL_LOG_DEADLINE, '<', $deadlineDate)
            ->where('compliance_project_controls.status', '!=', self::IMPLEMENTED)
            ->where('compliance_project_controls.status', '!=', self::UNDER_REVIEW)
            ->count();

        $data['allPassDueTasks'] = $allPassDueTasks;

        /* completed task percentage calculation */
        if ($data['implementedControls'] > 0 && $data['applicableControls'] > 0) {
            $data['completedTasksPercent'] = round($data['implementedControls'] * 100 / $data['applicableControls']);
        }

        return $data;
    }

    public function getCalendarTask(Request $request)
    {
        $today = RegularFunctions::getTodayDate();
        if (isset($request->filter_date))
        {
            $today = $request->filter_date;
        }

        $for_pdf = false;
        //calender task query
        if (isset($request->current_date_month) && !isset($request->date)) {
            $current_month = $request->current_date_month;
            $next_month = Carbon::createFromFormat('Y-m-d', $current_month)->addMonth()->toDateString();
            
            if(isset($request->filter_date)){
                $request->request->add(['date' => $request->filter_date]);
            }

            $calendarControls = $this->dashboardDataBaseQuery($request)
                                    ->where(self::PROJECT_CONTROL_LOG_APPLICABLE, 1)
                                    ->whereNotNull(self::PROJECT_CONTROL_LOG_DEADLINE)
                                    ->whereBetween(self::PROJECT_CONTROL_LOG_DEADLINE, [$current_month, $next_month])
                                    ->select('*', \DB::raw('true as new_controls'))
                                    // ->where('responsible', '!=', null)
                                    ->get();

            /**
             * fetch all the data that has frequency greater that One-Time
             * that does not have deadline on current month
             */
            $frequentCalendarControls = $this->dashboardDataBaseQuery($request)
                                            ->where(self::PROJECT_CONTROL_LOG_APPLICABLE, 1)
                                            ->whereNotNull(self::PROJECT_CONTROL_LOG_DEADLINE)
                                            ->whereNotBetween(self::PROJECT_CONTROL_LOG_DEADLINE, [$current_month, $next_month])
                                            ->select('*', \DB::raw('false as new_controls'))
                                            ->where('project_control_log.frequency', '!=', 'One-Time')
                                            ->get();

            // If single request is done, cannot attach the deadline where query and all data are fetched
            // Two different query at least ignores unnecessary One-Time data of different dates in reference to current date
            $calendarControls = $calendarControls->merge($frequentCalendarControls);
        } else if (isset($request->date)) {
            $calendarControls = $this->dashboardDataBaseQuery($request)
                                    ->where(self::PROJECT_CONTROL_LOG_APPLICABLE, 1)
                                    ->whereNotNull(self::PROJECT_CONTROL_LOG_DEADLINE)
                                    ->paginate(10);
        } else {
            $for_pdf = true;
            $calendarControls = $this->dashboardDataBaseQuery($request)
                                    ->where(self::PROJECT_CONTROL_LOG_APPLICABLE, 1)
                                    ->whereNotNull(self::PROJECT_CONTROL_LOG_DEADLINE)
                                    ->get();
        }

        $calendarTasks = [];

        $loop_count = 0;
        $loopdate = [];
        $loop_latest_deadline = null;
        $number_of_event_to_take = $for_pdf ? 'x' : '10';
        foreach ($calendarControls as $calendarControl) {
            if ($loop_latest_deadline !== $calendarControl->deadline)
                $loop_count = 0;
            $loop_latest_deadline = $calendarControl->deadline;
            $current_date_count = $loop_latest_deadline . $loop_count;
            if ($current_date_count !== $loop_latest_deadline . $number_of_event_to_take && !in_array($loop_latest_deadline, $loopdate)) {
                $controlId = is_null($calendarControl->id_separator) ? $calendarControl->primary_id : $calendarControl->primary_id . $calendarControl->id_separator . $calendarControl->sub_id;
                $taskStatusColor = '';
                // $today = date('Y-m-d');
                $url = route('compliance-project-control-show', [$calendarControl->project_id, $calendarControl->id]);
                $frequency = $calendarControl->frequency;

                // Not Implemented
                if (($calendarControl->status == self::NOT_IMPLEMENTED || $calendarControl->status == 'Rejected') && $calendarControl->deadline >= $today) {
                    $taskStatusColor = '#414141'; //  Black
                } elseif ($calendarControl->status == self::UNDER_REVIEW) {
                    // Under review
                    $taskStatusColor = '#5bc0de'; //  Blue
                } elseif ($calendarControl->deadline < $today && $calendarControl->status != self::IMPLEMENTED) {
                    // Late
                    $taskStatusColor = '#cf1110'; //  Red
                } elseif ($calendarControl->status == self::IMPLEMENTED) {
                    // Completed
                    $taskStatusColor = '#359f1d'; //  Green
                }

                // setting upcomming task date for task's with frequency other than `One-Time` task
                if ($frequency != 'One-Time') {
                    $nextReviewDate = '';
                    $currentMonth = new Carbon($request->current_date_month);
                    $currentDeadline = strtotime($calendarControl->deadline);
                    $deadlineMonth = new Carbon(substr($calendarControl->deadline,0,8).'01');

                    // for next year reflection
                    if($deadlineMonth->year < $currentMonth->year){
                        $date1 = strtotime($deadlineMonth->toDateString());
                        $date2 = strtotime($currentMonth->toDateString());
                        $deadlineDifference = 0;

                        while (($date1 = strtotime('+1 MONTH', $date1)) <= $date2){
                            $deadlineDifference++;
                        }
                    }
                    else{
                        $deadlineDifference = $currentMonth->month-$deadlineMonth->month;
                    }
                    if($currentMonth->month>=$deadlineMonth->month || $deadlineMonth->year < $currentMonth->year ){

                        switch ($frequency) {
                            case 'Monthly':
                                if($deadlineDifference == 0){

                                    $nextReviewDate = date('Y-m-d',$currentDeadline);
                                }else{
                                    $nextReviewDate = date('Y-m-d', strtotime('+'.$deadlineDifference.' month', $currentDeadline));
                                }
                                break;
                            case 'Every 3 Months':
                                if(!($deadlineDifference%3))
                                    $nextReviewDate = date('Y-m-d', strtotime('+'.$deadlineDifference.' month', $currentDeadline));
                                break;
                            case 'Bi-Annually':
                                if(!($deadlineDifference%6))
                                    $nextReviewDate = date('Y-m-d', strtotime('+'.$deadlineDifference.' month', $currentDeadline));
                                break;
                            case 'Annually':
                                if(!($deadlineDifference%12))
                                    $nextReviewDate = date('Y-m-d', strtotime('+'.$deadlineDifference.' month', $currentDeadline));
                                break;
                        }
                        /**
                         * Set all the task to black
                         * if the frequency task is less than 14 days of deadline
                         * before 14 days frequency task are not activated
                         */
                        $upcoming_task_showed=false;
                        if(!$calendarControl->new_controls){
                            $upcoming_task_showed=true;
                            // $url = '#';
                        }
                        if($today < date('Y-m-d',strtotime($nextReviewDate.' - 14 days') ) && $upcoming_task_showed){
                            $taskStatusColor = '#414141';
                        }
                        $calendarTasks[] = json_encode(['title' => decodeHTMLEntity($controlId).' '.decodeHTMLEntity($calendarControl->name), 'start' => $nextReviewDate, 'backgroundColor' => $taskStatusColor, 'textColor' => '#fff', 'url' => $url, 'status' => $calendarControl->status,'className'=>($upcoming_task_showed || $calendarControl->deleted_at) ?'disabled_click':'']);
                    }
                }else{
                    $calendarTasks[] = json_encode(['title' => addslashes(decodeHTMLEntity($controlId).' '.decodeHTMLEntity($calendarControl->name)),'control_id'=>$calendarControl->id, 'start' => $calendarControl->deadline, 'backgroundColor' => $taskStatusColor, 'textColor' => '#fff', 'url' => $url, 'status' => $calendarControl->status, 'className'=>$calendarControl->deleted_at ? 'disabled_click':'']);
                }

                $loop_count++;
            } else {
                array_push($loopdate, $loop_latest_deadline);
                $loop_count = 0;
            }
        }

        $data['calendarTasks'] = $calendarTasks;
        if ($for_pdf) {
            return $calendarTasks;
        }
        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        }

        return $data;
    }

    /**
     * getting the calendar more popover data
     */
    public function getCalendarMorePopoverData(Request $request)
    {
        $request->validate([
            'date' => 'required',
            'page' => 'required'
        ]);

        $calendarTasks = [];
        $currrentPage = $request->page;
        $pageLength = 10;

        $calendarControls = $this->dashboardDataBaseQuery($request)
            ->where(self::PROJECT_CONTROL_LOG_APPLICABLE, 1)
            ->where(self::PROJECT_CONTROL_LOG_DEADLINE, $request->date)
            ->select('*', \DB::raw('true as new_controls'))
            ->get();

        $frequencyControls = $this->dashboardDataBaseQuery($request)
            ->where(self::PROJECT_CONTROL_LOG_APPLICABLE, 1)
            ->select('*', \DB::raw('false as new_controls'))
            ->where('project_control_log.frequency','!=','One-Time')
            ->where(self::PROJECT_CONTROL_LOG_DEADLINE,'!=', $request->date)
            ->whereDay(self::PROJECT_CONTROL_LOG_DEADLINE,carbon::create($request->date)->day)
            ->get();

        $calendarControls = $calendarControls->merge($frequencyControls);

        foreach ($calendarControls as $key => $calendarControl) {
            $controlId = is_null($calendarControl->id_separator) ? $calendarControl->primary_id : $calendarControl->primary_id . $calendarControl->id_separator . $calendarControl->sub_id;
            $taskStatusColor = '';
            $today = date('Y-m-d');
            $url = route('compliance-project-control-show', [$calendarControl->project_id, $calendarControl->id]);
            $frequency = $calendarControl->frequency;
            // Not Implemented
            if (($calendarControl->status == self::NOT_IMPLEMENTED || $calendarControl->status == 'Rejected') && $calendarControl->deadline >= $today) {
                $taskStatusColor = '#414141'; //  Black
            } elseif ($calendarControl->status == self::UNDER_REVIEW) {
                // Under review
                $taskStatusColor = '#5bc0de'; //  Blue
            } elseif ($calendarControl->deadline < $today && $calendarControl->status != self::IMPLEMENTED) {
                // Late
                $taskStatusColor = '#cf1110'; //  Red
            } elseif ($calendarControl->status == self::IMPLEMENTED) {
                // Completed
                $taskStatusColor = '#359f1d'; //  Green
            }

            // setting upcoming task date for task's with frequency other than `One-Time` task
            if ($frequency != 'One-Time') {
                $nextReviewDate = '';
                $currentMonth = new Carbon($request->current_date_month);
                $currentMonth->modify('28 days');
                $currentDeadline = strtotime($calendarControl->deadline);
                $deadlineMonth = new Carbon(substr($calendarControl->deadline,0,8).'01');
                $deadlineDifference = $currentMonth->diffInMonths($deadlineMonth);

                switch ($frequency) {
                    case 'Monthly':
                        if($deadlineDifference == 0){

                            $nextReviewDate = date('Y-m-d',$currentDeadline);
                        }else{
                            $nextReviewDate = date('Y-m-d', strtotime('+'.$deadlineDifference.' month', $currentDeadline));
                        }
                        break;
                    case 'Every 3 Months':
                        if(!($deadlineDifference%3))
                            $nextReviewDate = date('Y-m-d', strtotime('+'.$deadlineDifference.' month', $currentDeadline));
                        break;
                    case 'Bi-Annually':
                        if(!($deadlineDifference%6))
                            $nextReviewDate = date('Y-m-d', strtotime('+'.$deadlineDifference.' month', $currentDeadline));
                        break;
                    case 'Annually':
                        if(!($deadlineDifference%12))
                            $nextReviewDate = date('Y-m-d', strtotime('+'.$deadlineDifference.' month', $currentDeadline));
                        break;
                }
                /**
                 * Set all the task to black
                 * if the frequency task is less than 14 days of deadline
                 * before 14 days frequency task are not activated
                 */

                $upcoming_task_showed=false;
                if(!$calendarControl->new_controls){
                    $upcoming_task_showed=true;
                }

                if($today < date('Y-m-d',strtotime($nextReviewDate.' - 14 days') ) && $upcoming_task_showed){
                    $taskStatusColor = '#414141';
                }
                $calendarTasks[] = json_encode(['title' => decodeHTMLEntity($controlId).' '.decodeHTMLEntity($calendarControl->name), 'start' => $nextReviewDate, 'backgroundColor' => $taskStatusColor, 'textColor' => '#fff', 'url' => $url, 'status' => $calendarControl->status,'className'=>($upcoming_task_showed || $calendarControl->deleted_at) ?'disabled_click':'']);
            }else{
                $calendarTasks[] = json_encode(['title' => addslashes(decodeHTMLEntity($controlId).' '.decodeHTMLEntity($calendarControl->name)),'control_id'=>$calendarControl->id, 'start' => $calendarControl->deadline, 'backgroundColor' => $taskStatusColor, 'textColor' => '#fff', 'url' => $url, 'status' => $calendarControl->status, 'className'=>$calendarControl->deleted_at ?'disabled_click':'']);
            }

        }


        if($currrentPage > 1){
            $paginationArray = array_slice($calendarTasks,($currrentPage-1)*$pageLength,$pageLength);
        }else{
            $paginationArray = array_slice($calendarTasks,0,$pageLength);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'currentPage' => $currrentPage,
                'pageCount' => ceil((count($calendarTasks) / $pageLength)),
                'totalCount' => count($calendarTasks),
                'calendarTasks' => $paginationArray
            ]
        ]);
    }

    private function dashboardDataBaseQuery($request, $filterCondition=null)
    {
        /* Selected projects*/
        $projectFilter =  explode(',', $request->projects);
        if(isset($request->date)) {
            $dataFilterDate = date("Y-m-d", strtotime($request->date));
        }
        else {
            $dataFilterDate = RegularFunctions::getTodayDate();
        }
        
        $projectControlLog = ComplianceProjectControlHistoryLog::from('compliance_project_controls_history_log as logs1')
        ->select('logs1.control_id as log_control_id', 'logs1.applicable', 'logs1.log_date', 'logs1.status', 'logs1.deadline', 'logs1.frequency', self::CONTROL_DELETED_DATE)
        ->where('log_date', function (Builder $query) use($dataFilterDate, $projectFilter, $filterCondition){
            $query->selectRaw('MAX(logs2.log_date)')
                ->from('compliance_project_controls_history_log as logs2')->whereRaw('logs1.control_id = logs2.control_id')->where('log_date', '<=', $dataFilterDate);

                $query->whereIn('project_id', $projectFilter);

                $query->when(isset($filterCondition['status']), function($q) use ($filterCondition) {
                    $q->where('status', $filterCondition['status']);
                });

                $query->when(isset($filterCondition['applicable']), function ($q) use ($filterCondition) {
                    $q->where('applicable', $filterCondition['applicable']);
                });

                $query->when(isset($filterCondition['deadline']), function ($q) use ($filterCondition) {
                    $q->where('deadline', $filterCondition['deadline']);
                });

                if(isset($filterCondition['frequency'])) {
                    $query->where('frequency', $filterCondition['frequency']);
                }
        })
        ->where(function($q) use($dataFilterDate) {
            $q->where(self::CONTROL_DELETED_DATE, '<', $dataFilterDate)
              ->orWhereNull(self::CONTROL_DELETED_DATE);
        });

        return ProjectControl::withTrashed()->withoutGlobalScope(DataScope::class)->joinSub($projectControlLog, 'project_control_log', function ($join) {
            $join->on('compliance_project_controls.id', '=', 'project_control_log.log_control_id');
        });
    }

    public function getDashboardData(Request $request)
    {
        $request->validate([
            'data_scope' => 'required',
            'projects' => 'nullable|string',
        ]);

        $data = $this->buildDashboardData($request);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function generatePdfReport(Request $request)
    {
        $request->validate([
            'data_scope' => 'required',
            'projects' => 'nullable',
        ]);

        $data = $this->buildDashboardData($request);
        $data['calendarTasks'] = $this->getCalendarTask($request);
        $calendarTasks = collect($data['calendarTasks']->getData()->data->calendarTasks)->map(function ($item) {
            $temp = json_decode($item);
            if (date_format(date_create($temp->start), 'F') == date('F')) {
                return $temp;
            }
        });
        $calendarTasks = $calendarTasks->filter(function ($value) {
            return !is_null($value);
        });

        $data['calendarTasks'] = $calendarTasks->groupBy('start');

        $pdf = \PDF::loadView('global.pdf-report', $data);
        $pdf->setOptions([
            'enable-local-file-access' => true,
            'enable-javascript' => true,
            'javascript-delay' => 5000,
            'enable-smart-shrinking' => true,
            'no-stop-slow-scripts' => true,
            'header-center' => 'Note: This is a system generated report',
            'footer-center' => 'Global Report',
            'footer-left' => 'Confidential',
            'footer-right' => '[page]',
        ]);

        Log::info('User has downloaded a global report.');

        return $pdf->download('global-report.pdf');
    }

    /*
    * Child department tree view data
    */
    public function departmentFilterTreeViewData(Request $request)
    {
        $request->validate([
            'data_scope' => 'required'
        ]);

        /* Handling data scoping data*/
        $dataScopeData = explode('-', $request->data_scope);
        $dataScopeType =  $dataScopeData[1] > 0 ? 'department' : 'organization';
        $parentId = ($dataScopeType == 'department') ? $dataScopeData[1] : 0;
        $departments = Department::where('parent_id', $parentId)->get();

        $treeViewData = $this->departmentFilterTreeViewBuilder($departments);

        return response()->json([
            'success' => true,
            'data' => $treeViewData
        ]);
    }

    /*
    * creating the tree structure tree view data
    */
    private function departmentFilterTreeViewBuilder($departments, $data = [])
    {
        foreach ($departments as $department) {
            $topNode = [
                'key' => $department->id,
                'title' => $department->name
            ];

            if ($department->departments()->count() > 0) {
                $childNode = self::departmentFilterTreeViewBuilder($department->departments);
                $topNode['children'] = $childNode;
            }

            $data[] = $topNode;
        }

        return $data;
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
        $isTodayDate = false;
        $todayDate = RegularFunctions::getTodayDate();

        if($request->filter_date){
            $filterDate = date("Y-m-d", strtotime($request->filter_date));
            $isTodayDate = $filterDate === $todayDate;
        }else{
            $filterDate = $todayDate;
            $isTodayDate = true;
        }

        /* When data scope selected is department */
        if ($departmentId != 0) {
            array_push($selectedDepartments, $departmentId);
        }

        $query = Project::withoutGlobalScopes();

        if($isTodayDate){
            $query->withoutTrashed();
        }else{
            $query->where(DB::raw("DATE_FORMAT(created_at,'%Y-%m-%d')"), '<=', $filterDate)
                ->where(function ($query) use ($filterDate) {
                    $query->where(DB::raw("DATE_FORMAT(deleted_at,'%Y-%m-%d')"), '>', $filterDate)
                        ->orWhereNull('deleted_at');
                });
        }
        
        $projects = $query->whereHas('department', function ($query) use ($departmentId, $selectedDepartments) {
                $query->where(function ($query) use ($departmentId, $selectedDepartments) {
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

    public function getAllProjectFilterDataWithoutDataScope(Request $request)
    {
        $request->validate([
            'selected_departments' => 'nullable'
        ]);

        $selectedDepartments = array_filter(explode(',', request('selected_departments')));

        $projects = Project::withoutGlobalScopes()
            ->whereHas('department', function ($query) use ($selectedDepartments) {
                $query->whereIn('department_id', $selectedDepartments)->orWhereNull('department_id');
            })->get();

        return response()->json([
            'success' => true,
            'data' => $projects
        ]);
    }
}
