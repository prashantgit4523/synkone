<?php

namespace App\Http\Controllers\Compliance\ImplementedControls;

use App\Models\DocumentAutomation\ControlDocument;
use App\Models\Integration\IntegrationControl;
use App\Traits\HasSorting;
use Illuminate\Support\Facades\DB;
use App\Traits\Compliance\ComplianceHelpers;
use Zip;
use Auth;
use Illuminate\Http\Request;
use App\Utils\RegularFunctions;
use App\Models\Compliance\Project;
use App\Models\Compliance\Standard;
use App\Http\Controllers\Controller;
use App\Models\Compliance\Evidence;
use Illuminate\Support\Facades\Storage;
use App\Models\Compliance\ProjectControl;
use Inertia\Inertia;
use App\Models\PolicyManagement\Campaign\Campaign;

class ImplementedControlsController extends Controller
{
    private $basePath = 'compliance.implemented-controls';
    private $loggedUser;

    use ComplianceHelpers;
    use HasSorting;

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->loggedUser = Auth::guard('admin')->user();
            return $next($request);
        });
    }

    public function index()
    {
        $taskContributors = RegularFunctions::getControlContributorList();
        $managedContributors[] = [
            'value' => 0,
            'label' => "All Users"
        ];
        foreach ($taskContributors as $key => $eachContributor) {
            $managedContributors[] = ['value' => $eachContributor, 'label' => $key];
        }

        $data = [
            'taskContributors' => $managedContributors,
        ];
        return Inertia::render('controls/controls', $data);
    }

    public function getControlsData()
    {
        $taskContributors = RegularFunctions::getControlContributorListArray();
        $allStandards = Standard::whereHas('projects', function ($query) {
            $query->whereHas('controls', function ($query) {
                if (!$this->loggedUser->hasAnyRole(['Global Admin', 'Compliance Administrator', 'Auditor'])) {
                    $query->where('responsible', $this->loggedUser->id)->orWhere('approver', $this->loggedUser->id);
                }
            });
        })->get();

        $managedStandards[] = [
            'value' => 0,
            'label' => "Select Standards"
        ];

        foreach ($allStandards as $key => $eachStandard) {
            $managedStandards[] = ['value' => $eachStandard['id'], 'label' => $eachStandard['name']];
        }
        return response()->json(compact('taskContributors', 'managedStandards', 'allStandards'));
    }

    public function getControlEvidences(Request $request)
    {
        $record = ProjectControl::where('id',$request->project_control_id)->first();

        return response()->json([
            'data' => $this->getImplementedControlActions($record)
        ]);
    }

    public function getImplementedControlsData(Request $request)
    {
        $baseQuery = ProjectControl::query()
            ->select(['compliance_project_controls.*', DB::raw('CONCAT(`primary_id`, `id_separator`, `sub_id`) as full_control_id'), DB::raw("
            CASE WHEN compliance_project_controls.automation = 'none' THEN (SELECT created_at FROM compliance_project_control_evidences WHERE compliance_project_control_evidences.type <> 'additional' AND compliance_project_controls.id = compliance_project_control_evidences.project_control_id ORDER BY created_at DESC LIMIT 1)
            WHEN compliance_project_controls.automation <> 'none' THEN (SELECT created_at FROM compliance_project_control_evidences WHERE compliance_project_control_evidences.type = 'additional' AND compliance_project_controls.id = compliance_project_control_evidences.project_control_id ORDER BY created_at DESC LIMIT 1)
            END AS last_uploaded
            ")])
            ->where('compliance_project_controls.status', 'Implemented')
            ->where('compliance_project_controls.applicable', true)
            ->join(DB::raw('compliance_projects as project'), 'compliance_project_controls.project_id', 'project.id')
            ->join(DB::raw('compliance_standards as standard'), 'project.standard_id', 'standard.id')
            ->with('control_evidences')
            ->where(function ($query) use ($request) {
                if (!$this->loggedUser->hasAnyRole(['Global Admin', 'Compliance Administrator', 'Auditor'])) {
                    $query->where('responsible', $this->loggedUser->id)->orWhere('approver', $this->loggedUser->id);
                }

                $controlName = $request->control_name;
                $controlID = $request->controlID;

                if ($controlName) {
                    $query->where('compliance_project_controls.name', 'LIKE', '%' . $controlName . '%');
                }

                if ($controlID) {
                    $query->whereRaw("CONCAT(`primary_id`, `id_separator`, `sub_id`) LIKE ?", ['%' . $controlID . '%']);
                }
            });

        $baseQuery->when($request->filled('responsible_user'), function () use ($request, $baseQuery) {
            $baseQuery->whereHas('responsibleUser', function ($query) use ($request) {
                $responsibleUser = $request->responsible_user;
                if ($this->loggedUser->hasAnyRole(['Global Admin', 'Compliance Administrator', 'Auditor', 'Contributor'])) {
                    $query->where('id', $responsibleUser);
                }
            });
        });

        $baseQuery->whereHas('project', function ($query) use ($request) {
            $standardId = $request->standard_id;
            $projectID = $request->project_id;

            if ($standardId) {
                $query->where('standard_id', $standardId);
            }
            if ($projectID) {
                $query->where('project_id', $projectID);
            }
        });

        $this->sort(['responsible', 'automation', 'description', 'full_control_id', 'name', 'project.name', 'standard.name', 'last_uploaded'], $baseQuery);

        $records = $baseQuery->paginate($request->per_page ?? 10);
        $data = [];

        foreach ($records as $record) {
            $controlNameObject = [
                'name' => $record->name,
                'url' => route('compliance-project-control-show', [$record->project->id, $record->id])
            ];

            $data[] = [
                $record->project->standard,
                $record->project->name,
                $record->controlId,
                $controlNameObject,
                $record->description,
                $record->automation,
                $record->last_uploaded ? date('d-m-Y, g:i a', strtotime($record->last_uploaded)) : '-', // last uploaded
                $record->responsibleUser ? $record->responsibleUser->full_name : '-',
                $this->getImplementedControlActions($record),
                $record->id
            ];
        }

        $records->setCollection(collect($data));
        return response()->json([
            'data' => $records
        ]);
    }

    public function downloadEvidences(Request $request, $controlID,$evidenceId=null)
    {
        $projectControl = ProjectControl::where(function ($query) {
            if (!$this->loggedUser->hasAnyRole(['Global Admin', 'Compliance Administrator', 'Auditor'])) {
                $query->where('responsible', $this->loggedUser->id)->orWhere('approver', $this->loggedUser->id);
            }
        })->findOrFail($controlID);

        $documentEvidences = Evidence::where('project_control_id', $projectControl->id)
            ->when($evidenceId, function ($query) use ($evidenceId) {
                $query->where('id',$evidenceId);
            })
            ->whereIn('type', ['document', 'additional'])
            ->when($projectControl->rejected_at, function ($query) use ($projectControl) {
                $query->where('created_at', '>', $projectControl->rejected_at);
            })
            ->get();

        if ($documentEvidences->count() == 1) {
            $evidence = $documentEvidences->first();
            // decrypting file
            $encryptedContents = Storage::get($evidence->path);
            $baseName = basename($evidence->path);
            $decryptedContents = decrypt($encryptedContents);

            return response()->streamDownload(function () use ($decryptedContents) {
                echo $decryptedContents;
            }, $baseName);
        }

        // for multiple evidences making zip

        $zipFileName = 'evidences' . time() . '.zip';
        $zipper = Zip::create($zipFileName);


        foreach ($documentEvidences as $evidence) {
            // decrypting file
            $encryptedContents = Storage::get($evidence->path);

            $baseName = basename($evidence->path);
            $decryptedContents = decrypt($encryptedContents);

            $zipper->addRaw($decryptedContents, $baseName);
        }

        return $zipper;
    }
}
