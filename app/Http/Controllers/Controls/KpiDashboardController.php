<?php

namespace App\Http\Controllers\Controls;

use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Rules\ValidDataScope;
use App\Models\Compliance\Project;
use App\Models\Compliance\Standard;
use App\Models\DataScope\DataScope;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\Compliance\ProjectControl;
use App\Models\Compliance\StandardControl;
use App\Models\Integration\Integration;
use App\Models\Controls\KpiControlStatus;
use Illuminate\Support\Facades\Validator;
use App\Models\Controls\KpiControlApiMapping;
use App\Models\Integration\IntegrationProvider;
use App\Traits\Integration\IntegrationApiTrait;

class KpiDashboardController extends Controller
{

    public function index(Request $request)
    {
        $integrationConnected = Integration::where('connected', true)->exists();

        $samaStandardId = Standard::where('name', 'SAMA Cyber Security Framework')->value('id');

        return Inertia::render('controls/Dashboard', compact('integrationConnected', 'samaStandardId'));
    }

    public function getDashboardData(Request $request)
    {
        $controls_data = [];
        $selectedStandardId = $request->standards[0];
        if ($selectedStandardId) {
            $kpi_controls = KpiControlStatus::query()
                ->whereHas('control', function ($query) use ($selectedStandardId) {
                    $query->whereHas('standard', function ($q) use ($selectedStandardId) {
                        $q->where('id', $selectedStandardId);
                    });
                })->with('kpi_mapping')->get();
            //Filtering KPI Controls
            foreach ($kpi_controls as $key => $kpiControl) {
                $controlId = $kpiControl->control_id;
                $standardControl = StandardControl::where('id', $controlId)->first();
                $projectControl = ProjectControl::query()
                    ->withoutGlobalScope(new DataScope())
                    ->where('primary_id', $standardControl->primary_id)
                    ->where('sub_id', $standardControl->sub_id)
                    ->where('applicable', 1)
                    ->where('status', 'Implemented')
                    ->where('automation', 'technical')
                    ->exists();
                if (!$projectControl) {
                    $kpi_controls->forget($key);
                }
            }
            foreach ($kpi_controls as $kpi_control) {
                $control_data = [];
                $control_data['total'] = $kpi_control->total;
                $control_data['per'] = $kpi_control->per;
                $control_data['contorl_status_id'] = $kpi_control->id;
                $control_data['name'] = $kpi_control->control->name;
                $control_data['controlId'] = $kpi_control->control->controlId;
                $control_data['control_id'] = $kpi_control->control_id;
                $control_data['id'] = $kpi_control->id;
                $control_data['targets'] = $kpi_control->kpi_mapping->targets;
                $control_data['description'] = $kpi_control->kpi_mapping->description;
                $control_data['type_of_total'] = $kpi_control->kpi_mapping->type_of_total;
                array_push($controls_data, $control_data);
                // $control_data['total'] = 30;
                // $control_data['per'] = 30;
                // $control_data['name'] = $kpi_control->control->name;
                // $control_data['controlId'] = $kpi_control->control->controlId;
                // $control_data['id'] = $kpi_control->id;
                // $control_data['targets'] = $kpi_control->targets;
                // $control_data['description'] = $kpi_control->description;
                // array_push($controls_data, $control_data);
            }
        }

        return response()->json($controls_data);
    }

    public function getStandardsFilterData(Request $request)
    {
        $request->validate([
            'data_scope' => ['required', new ValidDataScope],
            'selected_departments' => 'nullable'
        ]);

        $dataScope = explode('-', request('data_scope'));
        $selectedDepartments = explode(',', request('selected_departments'));
        $departmentId = $dataScope[1];


        $projectIds = StandardControl::query()
            ->whereHas('kpiControlStatuses')
            ->whereHas('standard.projects', function ($q) use ($selectedDepartments) {
                $q
                    ->withoutGlobalScope(new DataScope())
                    ->whereHas('department', function ($query) use ($selectedDepartments) {
                    $query->where(function ($query) use ($selectedDepartments) {
                        $query->whereIn('department_id', $selectedDepartments);

                        if (in_array('0', $selectedDepartments)) {
                            $query->orWhereNull('department_id');
                        }
                    });
                });
            })
            ->pluck('standard_id');

        $supported = [
            'UAE IA',
            'ISR V2',
            'SAMA Cyber Security Framework',
            'NCA ECC-1:2018',
            'NCA CSCC-1:2019',
            'SOC 2',
            'ISO/IEC 27001-2:2013',
            'PCI DSS 3.2.1',
            'PCI DSS 4.0',
            'CIS Critical Security Controls Group 1',
            'CIS Critical Security Controls Group 2',
            'CIS Critical Security Controls Group 3',
            'HIPAA Security Rule'
        ];

        $standards = Standard::whereIn('id', $projectIds)->whereIn('name', $supported)->get();

        return response()->json([
            'success' => true,
            'data' => $standards
        ]);
    }

    public function submitTarget(Request $request)
    {
        $filteredData = array_filter($request->values, 'strlen');
        $filteredDataWithValues = [
            'values' => $filteredData
        ];
        $validator = Validator::make($filteredDataWithValues, [
            'values' => 'required|array',
            'values.*' => 'numeric|between:0,100'
        ], [], ['values.*' => 'target values']);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => array_values($validator->errors()->messages())
            ]);
        }

        $targets = json_encode($filteredData);

        $controlId = $request->id;

        KpiControlApiMapping::where('control_id', $controlId)->update(['targets' => $targets]);

        return response()->json([
            'success' => true
        ]);
    }

    /**
     * export to pdf
     */
    public function generatePdfReport(Request $request)
    {
        $data['controls'] = $request->controls;
        foreach ($data['controls'] as $key => $dt) {
            $data['controls'][$key]['status'] = 'N/A';
            $data['controls'][$key]['target'] = 'N/A';
            if (!is_null($dt['targets'])) {
                $targets = json_decode($dt['targets'], true);
                if (array_key_exists(date('Y'), $targets)) {
                    $data['controls'][$key]['target'] = $targets[date('Y')];
                    $target = $targets[date('Y')];
                    $control_status = KpiControlStatus::where('id', $dt['contorl_status_id'])->first();
                    if ($control_status->per >= $target)
                        $data['controls'][$key]['status'] = 'Passed';
                    else
                        $data['controls'][$key]['status'] = 'Failed';
                }
            }
        }
        $pdf = \PDF::loadView('controls.kpi-dashboard.pdf-report', $data);

        $pdf->setOptions([
            'enable-local-file-access' => true,
            'enable-javascript' => true,
            'javascript-delay' => 3000,
            'enable-smart-shrinking' => true,
            'no-stop-slow-scripts' => true,
            'header-center' => 'Note: This is a system generated report',
            'footer-center' => 'KPI Control Report',
            'footer-left' => 'Confidential',
            'footer-right' => '[page]',
            'debug-javascript' => true,
        ]);

        Log::info('User has downloaded a kpi control report.');

        return $pdf->inline('kpi-control.pdf');
    }
}
