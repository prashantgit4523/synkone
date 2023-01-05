<?php

namespace App\Console\Commands\Compliance;

use App\Models\Compliance\Comment;
use App\Models\Compliance\Justification;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Compliance\Standard;
use App\Models\DataScope\Scopable;
use App\Models\Compliance\ProjectControl;
use App\Models\Compliance\Evidence;
use App\Models\RiskManagement\RiskMappedComplianceControl;
use App\Models\Compliance\ProjectControlMergeBackup;
use App\Traits\Compliance\DefaultStandardsInfo;
use App\Models\Compliance\ProjectControlEvidenceMergeBackup;
use App\Models\Compliance\ProjectControlCommentMergeBackup;
use App\Models\Compliance\ProjectControlJustificationMergeBackup;
use App\Models\RiskManagement\RiskMappedComplianceControlMergeBackup;
use App\Models\DataScope\ScopableControlMergeBackup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Utils\RegularFunctions;
use App\Models\Compliance\ComplianceProjectControlHistoryLog;

class ComplianceProjectControlMerge extends Command
{

    use DefaultStandardsInfo;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'compliance-project-controls:merge';

    protected $changeLogFileBasePath = 'database/seeders/Compliance/standards/standard_control_merge/';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->createControlFromUpdatedFile();
        $this->newLine();
        $this->info('Project(s) controls merge Task completed');

        return 0;
    }


    private function createControlFromUpdatedFile()
    {
        $standardWithMergeControl = $this->getStandards();
        $updatedStandards = $this->getDefaultStandards();

        foreach ($standardWithMergeControl as $key => $standard) {
            $matchedStandard = Standard::where('name', $standard['name'])->where('version', $standard['version'])->with(['projects'])->first();
            $standardProjects = $matchedStandard->projects;

            if (count($standardProjects) == 0) {
                continue;
            }

            $matchedDefaultStandard = array_filter($updatedStandards, function ($ar) use ($standard) {
                return ($ar['name'] === $standard['name'] && $ar['version'] == $standard['version']);
            });

            if (empty($matchedDefaultStandard)) {       
                continue;
            }

            $matchedDefaultStandard = reset($matchedDefaultStandard);
            $changeLogExcelData = Excel::toCollection(collect([]), base_path($standard['change_log_file_path']))->first();
            $controlMergeData = [];


            /* Building the collection of key value pair of change log file */
            foreach ($changeLogExcelData as $key => $row) {
                if ($key == 0) {
                    continue;
                }
                $controlToBeMerged = explode("\n", $row[0]);
                $controlToBeMerged = array_map('trim', $controlToBeMerged);
                $controlMergeData[] = [
                    'controls_to_be_merged' => $controlToBeMerged,
                    'new_control' => trim($row[1]),
                    'keep_evidences_from' => trim($row[2]),
                ];
            }
            unset($row);

            /* Creating the collection of key value pair of updated standard controls */
            $updatedStandardControlExcelData = Excel::toCollection(collect([]), base_path($matchedDefaultStandard['controls_path']))->first();
            $updatedStandardControlExcelRows = $updatedStandardControlExcelData->map(function ($row) {
                return [
                    'primary_id' => (string)$row[0],
                    'sub_id' => (string)$row[1],
                    'name' => (string)$row[2],
                    'description' => (string)$row[3]
                ];
            });

            $updatedStandardDuplicateControls = [];

            // Finding if standard csv has duplicate controls id
            foreach ($updatedStandardControlExcelRows as $index => $row) {
                if ($index == 0) {
                    continue;
                }
                $targetPrimaryId = $row['primary_id'];
                $targetSubId = $row['sub_id'];


                foreach ($updatedStandardControlExcelRows as $key => $rowItem) {
                    /* skipping the first iteration and also the control on check index */
                    if ($key == 0 || $key == $index) {
                        continue;
                    }

                    if ($targetPrimaryId === $rowItem['primary_id'] && $targetSubId === $rowItem['sub_id']) {
                        $updatedStandardDuplicateControls [] = $index + 1;
                    }
                }
            }
            unset($row);

            /* Skipping the update of current standard controls and projects control if csv file has duplicate entries */
            if (!empty($updatedStandardDuplicateControls)) {    
                $logMsg = 'Standard >> ' . $matchedStandard->name . ' ' . $matchedStandard->version . ' is skipped because its xlxs file has duplicate controlId(s) in row number ' . implode(",", $updatedStandardDuplicateControls);


                $this->error($logMsg);
                Log::error($logMsg);

                continue;
            }


            /* UPDATING PROJECTS */
            foreach ($standardProjects as $key => $project) {
                DB::beginTransaction();

                try {
                    $this->mergeProjectControls($project, $updatedStandardControlExcelRows, $controlMergeData, $matchedDefaultStandard);

                    DB::commit();

                    $logMsg = 'Controls of Project `' . $project->name . '`' . ' has been merged successfully!';
                    $this->info($logMsg);
                    Log::info($logMsg);
                } catch (\Exception $e) {

                    DB::rollback();
                    $logMsg = 'Controls of Project `' . $project->name . '`' . ' not able to merge!';
                    $this->error($logMsg);
                    $this->error($e->getMessage());
                    Log::error($logMsg);
                    Log::error($e->getMessage());
                    Log::error($e->getFile());
                    Log::error($e->getLine());
                }
            }
            unset($project);
        }
        unset($standard);

    }


    /*
    * Loop through the updated standard control Excel file data and just check the change log data
    * to find which controls need to delete and update in order to create the effect of merging
    **/
    private function mergeProjectControls($project, $updatedStandardControlExcelRows, $controlsToBeMergedExcelData, $matchedDefaultStandard)
    {
        $projectDataScope = $project->scope;
        $controlSeperator = $matchedDefaultStandard['controls_seperator'];
        $controlsToBeBackup = [];
        $controlEvidencesToBackup = [];
        $controlCommentsToBackup = [];
        $controlJustificationToBackup = [];
        $controlMappedRiskToBackup = [];
        $dataScopeToBackup = [];
        $scopableDataToDelete = [];
        $controlsToDelete = [];
        ProjectControl::where('project_id', $project->id)->get();
        $mapRiskToNewControl = [];
        $mergedControlEvidences = [];
        $mergedControlComments = [];
        $mergedControlJustifications = [];
        $statusOptions = array( "Implemented", "Under Review", "Rejected", "Not Implemented" );

        foreach ($updatedStandardControlExcelRows as $controlIndex => $excelRow) {
            if ($controlIndex == 0) {
                continue;
            }

            $primaryID = $excelRow['primary_id'];
            $name = $excelRow['name'];
            $subID = $excelRow['sub_id'];
            $description = $excelRow['description'];

            $controlMergeInfo = collect($controlsToBeMergedExcelData)->filter(function ($row) use ($primaryID, $controlSeperator, $subID) {
                $controlIdInCheck = ($primaryID . $controlSeperator . $subID);
                return ($controlIdInCheck == $row['new_control']) ? $row : NULL;
            })->first();


            /* when control is not in change log file*/
            if (is_null($controlMergeInfo)) {
                continue;
            }

            /* Getting the control id(s) to be Merged */
            $controlIdsToBeMerged = $controlMergeInfo['controls_to_be_merged'];

            /* Creating the missing control */
            if (strtolower($controlIdsToBeMerged[0]) == "missing") {
                /* Creating the missing control */
                $newProjectControl = ProjectControl::firstOrCreate([
                    'project_id' => $project->id,
                    'index' => $controlIndex,
                    'primary_id' => $primaryID,
                    'id_separator' => $controlSeperator,
                    'sub_id' => $subID,
                    'name' => $name,
                    'description' => $description,
                ]);

                // To create compliance project cocntrol history log
                $todayDate = RegularFunctions::getTodayDate();
                $controlChangeLog = ComplianceProjectControlHistoryLog::where('log_date', $todayDate)->where('control_id', $newProjectControl->id)->first();
                $changeLogData = [
                    'project_id' => $project->id,
                    'control_id' => $newProjectControl->id,
                    'applicable' => '1',
                    'log_date' => $todayDate,
                    'control_created_date' => $newProjectControl->created_at,
                    'status' => $newProjectControl->status,
                    'deadline' => $newProjectControl->deadline,
                    'frequency' => $newProjectControl->frequency
                ];
                if (!is_null($controlChangeLog)) {;
                    $changeLogData['updated_at'] = date('Y-m-d H:i:s');
                    $controlChangeLog->update($changeLogData);
                } else {
                    $changeLogData['created_at'] = date('Y-m-d H:i:s');
                    $changeLogData['updated_at'] = date('Y-m-d H:i:s');
                    ComplianceProjectControlHistoryLog::create($changeLogData);
                }

                Scopable::create([
                    "organization_id" => $projectDataScope->organization_id,
                    "department_id" => $projectDataScope->department_id,
                    "scopable_id" => $newProjectControl->id,
                    "scopable_type" => 'App\Models\Compliance\ProjectControl'
                ]);

                continue;
            }

            $controlsToBeMerge = ProjectControl::where('project_id', $project->id)->where(function ($query) use ($controlIdsToBeMerged) {
                $query->whereIn(DB::raw('CONCAT(primary_id, id_separator, sub_id)'), (array)$controlIdsToBeMerged);
            })->with(['evidences', 'comments', 'justifications', 'risks'])->get();

            /* WHEN CONTROLS TO BE MERGE NOT FOUND IN DB */
            if ($controlsToBeMerge->count() == 0) {
                continue;
            } elseif ($controlsToBeMerge->count() == 1) {
                /*
                    Handling control to be merged id and new control's DB auto ncreamenting id is same
                    Finding controls primary id is equal to control id of controls to be merge
                    to identify if already merged and command is being run for second Time
                */

                /* Finding the project control with updated control id */
                $projectControl = ProjectControl::where('project_id', $project->id)->where('primary_id', $primaryID)->where('sub_id', $subID)->first();

                if (!is_null($projectControl) && $projectControl->id == $controlsToBeMerge->first()->id) {
                    continue;
                }
            }

            $isNoControlToKeepEvidence = $controlMergeInfo['keep_evidences_from'] == "" ? true : false;
            $controlIdTokeepEvidencesFrom = explode("\n", $controlMergeInfo['keep_evidences_from']);
            $controlIdTokeepEvidencesFrom = array_map('trim', $controlIdTokeepEvidencesFrom);
            $controlsTokeepEvidences = $controlsToBeMerge->filter(function ($control) use ($controlIdTokeepEvidencesFrom) {
                $controlToBeMergedID = $control->primary_id . $control->id_separator . $control->sub_id;
                return (in_array($controlToBeMergedID, $controlIdTokeepEvidencesFrom));
            });

            foreach ($controlsToBeMerge as $key => $control) {
                $controlIdInCheck = $control->primary_id . $controlSeperator . $control->sub_id;

                /* WHEN THERE IS NO CONTROL ID TO KEEP EVIDENCES FROM IS Specified */
                if ($isNoControlToKeepEvidence) {
                    if (!is_null($control->responsible) && !is_null($control->approver)) {
                        $mergedControl = $control;
                        break;
                    }
                    $mergedControl = $control;
                    break;
                } else {
                    /* WHEN THERE IS CONTROL TO KEEP EVIDENCES FROM IS SPECIFIED */

                    if (in_array($controlIdInCheck, $controlIdTokeepEvidencesFrom)) {

                        if (!is_null($control->responsible) && !is_null($control->approver)) {
                            $mergedControl = $control;
                            break;
                        }
                        $mergedControl = $control;
                        break;
                    }
                }
            }

            $numberOfImplementedControls = 0;
            $numberOfUnderReviewControls = 0;
            $numberOfRejectedControls = 0;
            $mergedControlHasEvidence = 0;
            $controlsStatusDeadlineArray = [];

            foreach ($controlsToBeMerge as $key => $control) {
                $controlDataScope = $control->scope;
                $scopableDataToDelete[] = $controlDataScope->id;
                if ($mergedControl->id != $control->id) {
                    $controlsToDelete[] = $control->id;
                }

                $controlToBeMergedID = $control->primary_id . $control->id_separator . $control->sub_id;
                $isEvidencesKeepable = (in_array($controlToBeMergedID, $controlIdTokeepEvidencesFrom));

                $dataScopeToBackup[] = [
                    "id" => $controlDataScope->id,
                    "organization_id" => $controlDataScope->organization_id,
                    "department_id" => $controlDataScope->department_id,
                    "scopable_id" => $controlDataScope->scopable_id,
                    "scopable_type" => $controlDataScope->scopable_type,
                    "created_at" => $controlDataScope->created_at,
                    "updated_at" => $controlDataScope->updated_at,
                ];

                $controlsToBeBackup[] = [
                    'id' => $control->id,
                    "project_id" => $control->project_id,
                    "index" => $control->index,
                    "name" => $control->name,
                    "primary_id" => $control->primary_id,
                    "id_separator" => $control->id_separator,
                    "sub_id" => $control->sub_id,
                    "description" => $control->description,
                    "required_evidence" => $control->required_evidence,
                    "applicable" => $control->applicable,
                    "is_editable" => $control->is_editable,
                    "current_cycle" => $control->current_cycle,
                    "status" => $control->status,
                    "responsible" => $control->responsible,
                    "approver" => $control->approver,
                    "deadline" => $control->deadline,
                    "frequency" => $control->frequency,
                    "amend_status" => $control->amend_status,
                    "approved_at" => $control->approved_at,
                    "rejected_at" => $control->rejected_at,
                    "unlocked_at" => $control->unlocked_at,
                    "created_at" => $control->created_at,
                    "updated_at" => $control->updated_at,
                ];

                /* Creating mapped risks data to back up  */
                foreach ($control->risks as $key => $risk) {
                    $controlMappedRiskToBackup[] = [
                        "control_id" => $risk->pivot->control_id,
                        "risk_id" => $risk->pivot->risk_id,
                        "created_at" => $risk->pivot->created_at,
                        "updated_at" => $risk->pivot->updated_at,
                    ];

                    /* Mapping rik(s) to new control  */
                    if ($mergedControl->id != $control->id) {
                        $mapRiskToNewControl[] = [
                            "control_id" => $mergedControl->id,
                            "risk_id" => $risk->pivot->risk_id,
                        ];
                    }
                }

                /* Creating evidences data to back up  */
                foreach ($control->evidences as $evidence) {
                    $controlEvidencesToBackup[] = [
                        "id" => $evidence->id,
                        "project_control_id" => $evidence->project_control_id,
                        "name" => $evidence->name,
                        "path" => $evidence->path,
                        "type" => $evidence->type,
                        "text_evidence" => $evidence->text_evidence,
                        "status" => $evidence->status,
                        "deadline" => $evidence->deadline,
                        "created_at" => $evidence->created_at,
                        "updated_at" => $evidence->updated_at,
                    ];
                }

                /* Creating comments data to backup  */
                foreach ($control->comments as $comment) {
                    $controlCommentsToBackup[] = [
                        "id" => $comment->id,
                        "project_control_id" => $comment->project_control_id,
                        "from" => $comment->from,
                        "to" => $comment->to,
                        "comment" => $comment->comment,
                        "created_at" => $comment->created_at,
                        "updated_at" => $comment->updated_at,
                    ];

                    // Comments will be kept only if we are keeping evidences from a single controller.
                    if ($isEvidencesKeepable && $mergedControl->id != $control->id &&
                        !$mergedControl->comments->contains('comment', $comment->comment) &&
                        count($controlIdTokeepEvidencesFrom) === 1
                    ) {
                        $mergedControlComments[] = [
                            "project_control_id" => $mergedControl->id,
                            "from" => $comment->from,
                            "to" => $comment->to,
                            "comment" => $comment->comment,
                            "created_at" => $comment->created_at,
                            "updated_at" => $comment->updated_at,
                        ];
                    }
                }

                //delete main control comments if they exist, if we keep evidences from more than one control.
                if (count($controlIdTokeepEvidencesFrom) > 1) {
                    $mergedControl->comments()->delete();
                }

                /* Creating justifications data to back up  */
                foreach ($control->justifications as $key => $justification) {
                    $controlJustificationToBackup[] = [
                        "id" => $justification->id,
                        "project_control_id" => $justification->project_control_id,
                        "justification" => $justification->justification,
                        "for" => $justification->for,
                        "creator_id" => $justification->creator_id,
                        "created_at" => $justification->created_at,
                        "updated_at" => $justification->updated_at,
                    ];

                    if ($isEvidencesKeepable && $mergedControl->id != $control->id && !$mergedControl->justifications->contains('comment', $justification->justification)) {
                        $mergedControlJustifications[] = [
                            "project_control_id" => $mergedControl->id,
                            "justification" => $justification->justification,
                            "for" => $justification->for,
                            "creator_id" => $justification->creator_id,
                            "created_at" => $justification->created_at,
                            "updated_at" => $justification->updated_at,
                        ];
                    }

                }              

                if ($control->status === $statusOptions[0]) {
                    $numberOfImplementedControls++;
                } elseif($control->status === $statusOptions[1]) {
                    $numberOfUnderReviewControls++;
                }elseif($control->status === $statusOptions[2]) {
                    $numberOfRejectedControls++;
                }

                if($control->deadline && ($isEvidencesKeepable || in_array($control->status, [$statusOptions[0], $statusOptions[1]]))) {
                    $controlsStatusDeadlineArray[] = [
                        "control_key" => $key,
                        "deadline" => $control->deadline,
                        "status" => $control->status
                    ];
                }
            }

            $transferableEvidences = $this->getTransferableEvidences($controlsTokeepEvidences, $mergedControl);
            if ($transferableEvidences) {
                $mergedControlHasEvidence++;
            }

            $mergedControlEvidences = [...$mergedControlEvidences, ...$transferableEvidences];

            /* Deleting the evidences type control or linked evidences of merged control*/
            $mergedControl->evidences()->where('type', 'control')->delete();

            /*
                It supposes to below the backup otherwise controls attribute will be backed up as updated one
             */

            //status of new control
            $newControlStatus = $statusOptions[3];
            if($mergedControl->evidences()->exists() || $mergedControlHasEvidence > 0){
                if($numberOfImplementedControls >= 1) {
                    $newControlStatus = $statusOptions[0];
                } elseif($numberOfUnderReviewControls >= 1) {
                    $newControlStatus = $statusOptions[1];
                }elseif($numberOfRejectedControls >= 1){
                    $newControlStatus = $statusOptions[2];
                }
            }

            $controlsStatusDeadlineArray = array_filter($controlsStatusDeadlineArray, function ($control) use ($newControlStatus) {
                return ($control['status'] == $newControlStatus);
            });

            $controlData = [];
            if($controlsStatusDeadlineArray){
                $idForControlDetailsToKeep = null;
                $newControlsDeadline = null;
                foreach($controlsStatusDeadlineArray as $controlStatusDeadline){
                    $thisControlDeadline = new Carbon($controlStatusDeadline['deadline']);
                    if($newControlsDeadline == null || $newControlsDeadline->lessThan($thisControlDeadline)){
                        $newControlsDeadline = $thisControlDeadline;
                        $idForControlDetailsToKeep = $controlStatusDeadline['control_key'];
                    }
                }

                if($newControlsDeadline->lessThan(Carbon::now()->addDays(7)) && $newControlStatus != $statusOptions[0]){
                    $newControlsDeadline = Carbon::now()->addDays(7);
                }

                $dataKeepingControl = $controlsToBeMerge[$idForControlDetailsToKeep];
                if($dataKeepingControl){
                    $controlData[] = [
                        'deadline' => $newControlsDeadline->format('Y-m-d'),
                        'frequency' => $dataKeepingControl->frequency,
                        'applicable' => $dataKeepingControl->applicable,
                        'is_editable' => $dataKeepingControl->is_editable,
                        'current_cycle' => $dataKeepingControl->current_cycle,
                        'responsible' => $dataKeepingControl->responsible,
                        'approver' => $dataKeepingControl->approver,
                        'approved_at' => $dataKeepingControl->approved_at,
                        'rejected_at' => $dataKeepingControl->rejected_at
                    ];

                }
            }

            $controlData[] = [
                'index' => $controlIndex,
                'primary_id' => $primaryID,
                'sub_id' => $subID,
                'name' => $name,
                'status' => $newControlStatus,
                'description' => $description,
            ];

            $controlData = array_merge(...$controlData);

            $mergedControl->update($controlData);
        }

        /* Filtering unique control name within the project control id or only keeping the evidences with unique name merged control out of control to be merged evidences */
        $mergedControlUniqueNameEvidences = collect($mergedControlEvidences)->unique(function ($item) {
            return $item['project_control_id'] . $item['name'];
        })->toArray();

        /* Backing up the data */
        Evidence::insert($mergedControlUniqueNameEvidences);
        Comment::insert($mergedControlComments);
        Justification::insert($mergedControlJustifications);
        RiskMappedComplianceControl::insert($mapRiskToNewControl);
        ProjectControlMergeBackup::insert($controlsToBeBackup);
        ProjectControlEvidenceMergeBackup::insert($controlEvidencesToBackup);
        ProjectControlCommentMergeBackup::insert($controlCommentsToBackup);
        ProjectControlJustificationMergeBackup::insert($controlJustificationToBackup);
        RiskMappedComplianceControlMergeBackup::insert($controlMappedRiskToBackup);
        ScopableControlMergeBackup::insert($dataScopeToBackup);

        /* Deleting the control history log first */
        $todayDate = RegularFunctions::getTodayDate();
        ComplianceProjectControlHistoryLog::whereIn('control_id', $controlsToDelete)->where('log_date', $todayDate)->delete();
        $controls = ProjectControl::whereIn('id', $controlsToDelete)
        ->get()->map(function ($control) use ($todayDate)
        {
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

        /* Deleting the controls */
        ProjectControl::whereIn('id', $controlsToDelete)->delete();

        /* reindex controls */
        $this->reIndexProjectControls($project, $updatedStandardControlExcelRows);
    }


    /*
    * Filtering evidences  and transforming to merged control
    */
    private function getTransferableEvidences($controlsTokeepEvidences, $mergedControl)
    {
        $transferableEvidences = [];
        $pulledEvidencesLinkedControls = [];
        foreach ($controlsTokeepEvidences as  $controlTokeepEvidence) {
            /*
                Keeping the evidences for merged control
             */
            foreach ($controlTokeepEvidence->evidences as $evidence) {
                /* skipping the evidences of merged control */
                /* skipping if evidences has same name as that of merged control evidences */
                if ($mergedControl->id == $controlTokeepEvidence->id && $evidence->type != 'control') {
                    continue;
                }

                if ($evidence->type == 'control') {

                    if (!in_array($evidence->path, $pulledEvidencesLinkedControls)) {
                        /* Pull the evidences from linked control(s) */
                        [$pulledEvidences, $pulledControls] = $this->pullEvidencesOfLinkedControls($evidence->path, $pulledEvidencesLinkedControls);
                        $pulledEvidencesLinkedControls = [...$pulledEvidencesLinkedControls, ...$pulledControls];

                        foreach ($pulledEvidences as $key => $pulledEvidence) {
                            $isDocumentEvidence = $pulledEvidence['type'] == 'document';
                            $pulledEvidencePath = $pulledEvidence['path'];

                            if ($isDocumentEvidence) {
                                $fileName = basename($pulledEvidencePath);
                                $fileEvidencePath = "private/compliance/evidences/{$mergedControl->id}/{$fileName}";

                                if (!Storage::exists($fileEvidencePath)) {
                                    Storage::copy($pulledEvidencePath, $fileEvidencePath);
                                }
                            }

                            $transferableEvidences[] = [
                                "project_control_id" => $mergedControl['id'],
                                "name" => $pulledEvidence['name'],
                                "path" => $fileEvidencePath ?? $pulledEvidence['path'],
                                "type" => $pulledEvidence['type'],
                                "text_evidence" => $pulledEvidence['text_evidence'],
                                "status" => $pulledEvidence['status'],
                                "deadline" => $pulledEvidence['deadline'],
                                "created_at" => $pulledEvidence['created_at'],
                                "updated_at" => $pulledEvidence['updated_at'],
                            ];
                        }
                    }

                    continue;
                }

                $transferableEvidences[] = [
                    "project_control_id" => $mergedControl->id,
                    "name" => $evidence->name,
                    "path" => $evidence->path,
                    "type" => $evidence->type,
                    "text_evidence" => $evidence->text_evidence,
                    "status" => $evidence->status,
                    "deadline" => $evidence->deadline,
                    "created_at" => $evidence->created_at,
                    "updated_at" => $evidence->updated_at,
                ];
            }
        }


        return $transferableEvidences;
    }


    /*
    * Pull the evidences of linked control(s)
    */
    private function pullEvidencesOfLinkedControls($linkedControlId, $pulledLinkedControls = [], $pulledEvidences = [])
    {
        $pulledLinkedControls[] = $linkedControlId;
        $evidences = Evidence::where('project_control_id', $linkedControlId)->get();

        if (count($evidences) == 0) {
            $evidences = ProjectControlEvidenceMergeBackup::where('project_control_id', $linkedControlId)->get();
        }

        foreach ($evidences as $evidence) {
            if ($evidence->type == 'control') {
                if (!in_array($evidence->path, $pulledLinkedControls)) {
                    $childItems = $this->pullEvidencesOfLinkedControls($evidence->path, $pulledLinkedControls, $pulledEvidences);
                    $pulledEvidences = [...$pulledEvidences, ...$childItems[0]];
                    $pulledLinkedControls = [...$pulledLinkedControls, ...$childItems[1]];
                }

                continue;
            }

            $pulledEvidences[] = [
                "name" => $evidence->name,
                "path" => $evidence->path,
                "type" => $evidence->type,
                "text_evidence" => $evidence->text_evidence,
                "status" => $evidence->status,
                "deadline" => $evidence->deadline,
                "created_at" => $evidence->created_at,
                "updated_at" => $evidence->updated_at,
            ];
        }

        return [
            $pulledEvidences,
            $pulledLinkedControls
        ];
    }


    private function reIndexProjectControls($project, $updatedStandardControlExcelRows)
    {
        foreach ($updatedStandardControlExcelRows as $controlIndex => $excelRow) {
            if ($controlIndex == 0) {
                continue;
            }

            $primaryID = $excelRow['primary_id'];
            $subID = $excelRow['sub_id'];

            /* Finding the project control with updated control id */
            $updatedProjectControl = ProjectControl::where('project_id', $project->id)->where('primary_id', $primaryID)->where('sub_id', $subID)->first();

            if (is_null($updatedProjectControl)) {
                continue;
            }

            $updatedProjectControl->update([
                'index' => $controlIndex
            ]);
        }
    }

    private function getStandards()
    {
        return [
            [
                'name' => 'UAE IA',
                'version' => 'V1.0',
                'change_log_file_path' => $this->changeLogFileBasePath . 'UAE Merge Changelog.xlsx'
            ],
            [
                'name' => 'ISR V2',
                'version' => 'V2.0',
                'change_log_file_path' => $this->changeLogFileBasePath . 'ISR Merge Changelog.xlsx'
            ]
        ];
    }
}
