<?php

namespace App\Http\Controllers\Compliance;

use App\Traits\HasSorting;
use Illuminate\Http\Request;
use App\Models\DataScope\DataScope;
use App\Http\Controllers\Controller;
use App\Models\Compliance\ProjectControl;
use Illuminate\Support\Facades\DB;

class ComplianceController extends Controller
{
    use HasSorting;

    public function getAllComplianceControls(Request $request, $projectControlId)
    {
        $keyword = $request->search ?? null;

        $projectControl = ProjectControl::withoutGlobalScope(DataScope::class)->findOrFail($projectControlId);

        $controlsToExclude = $projectControl->evidences()->where('type', 'control')->pluck('path')->toArray();
        $controlsToExclude[] = $projectControlId;

        $projectControlsQuery = ProjectControl::query()
            ->join('compliance_projects', 'compliance_project_controls.project_id', 'compliance_projects.id')
            ->join('compliance_standards', 'compliance_projects.standard_id', 'compliance_standards.id')
            ->select([
                DB::raw('CONCAT_WS(id_separator, primary_id, sub_id) AS full_control_id'),
                'compliance_projects.name AS project_name',
                'compliance_standards.name AS standard',
                'compliance_project_controls.id AS id',
                'compliance_project_controls.name AS name',
                'compliance_project_controls.description AS description',
                'compliance_project_controls.frequency AS frequency',
                'compliance_project_controls.status AS status'
            ])
        ->where(function ($q) use ($request, $controlsToExclude, $keyword) {
            $q->whereNotIn('compliance_project_controls.id', $controlsToExclude);

            $q->where('compliance_project_controls.applicable', 1);

            if ($request->project_filter) {
                $q->where('compliance_project_controls.project_id', $request->project_filter);
            }
            if ($keyword) {
                $q->where('compliance_project_controls.name', 'LIKE', "%{$keyword}%")->orWhere(\DB::raw("CONCAT_WS(id_separator, primary_id, sub_id)"), 'LIKE', "%{$keyword}%");
            }
        })->whereHas('project', function ($q) use ($request) {
            if ($request->standard_filter) {
                $q->where('standard_id', $request->standard_filter);
            }
        });

        $this->sort(['project_name', 'standard', 'full_control_id', 'description', 'name', 'description', 'frequency', 'status'], $projectControlsQuery);
        $projectControls = $projectControlsQuery->paginate($request->input('per_page') ?? 10);

        $projectControls->getCollection()->transform(function ($item) {
            $standard = $item->standard ?: '';
            $projectName = $item->project_name ?: '';
            $controlName = $item->name ?: '';
            if ($item->status == 'Not Implemented') {
                $status = "<span class='badge task-status-red w-100'>" . $item->status . '</span>';
            } elseif ($item->status == 'Implemented') {
                $status = "<span class='badge task-status-green w-100'>" . $item->status . '</span>';
            } elseif ($item->status == 'Rejected') {
                $status = "<span class='badge task-status-orange w-100'>" . $item->status . '</span>';
            } else {
                $status = "<span class='badge task-status-blue w-100'>" . $item->status . '</span>';
            }

            return [
                'project_name' => $projectName,
                'standard' => $standard,
                'control_id' => $item->full_control_id,
                'control_name' => $controlName,
                'desc' => $item->description ?: '',
                'frequency' => $item->frequency,
                'status' => $status,
                'project_control_id' => $item->id,
                'select' => ''
            ];
        });

        return response()->json(['data' => $projectControls]);
    }
}
