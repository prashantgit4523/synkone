<?php

namespace App\Console\Commands\Compliance;

use Illuminate\Console\Command;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Compliance\Standard;
use App\Models\Compliance\ProjectControl;
use App\Models\Compliance\ProjectControlBackup;
use App\Models\Compliance\CommentBackup;
use App\Models\Compliance\EvidenceBackup;
use App\Models\Compliance\JustificationBackup;
use Illuminate\Support\Str;
use App\Models\DataScope\Scopable;
use App\Models\DataScope\ScopableBackup;
use App\Models\RiskManagement\RiskMappedComplianceControlBackup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Traits\Compliance\DefaultStandardsInfo;

class ComplianceProjectControlUpdate extends Command
{
    use DefaultStandardsInfo;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'compliance-project-controls:update {--o|old}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command fixes the duplicate control(s) and updates the compliance project control(s)';

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
        $this->updateProjectControls();

        return 0;
    }

    private function updateProjectControls()
    {
        if($this->option('old')){
            $standards = $this->getDefaultStandards();
            foreach($standards as &$standard) {
                if($standard["controls_path"] === $this->standardBasePath.'UAE_IA-_DOT.xlsx'){
                    $standard["controls_path"] = $this->standardBasePath.'UAE_IA-_DOT_Old.xlsx';
                }

                if($standard["controls_path"] === $this->standardBasePath.'ISR V2-DOT.xlsx'){
                    $standard["controls_path"] = $this->standardBasePath.'ISR V2-DOT_Old.xlsx';
                }
            }
            unset($standard);
        } else {
            $standards = $this->getDefaultStandards();
        }

        foreach ($standards as $key => $standard) {
            $matchedStandard = Standard::where('name', $standard['name'])->where('version', $standard['version'])->with('projects')->first();

            if (is_null($matchedStandard)) {
                continue;
            }

            $projects = $matchedStandard->projects;
            $updateProjectControls = $projects->count() > 0 ? true : false;

            /* Skipping to next iteration when standard has no project(s) */
            if (!$updateProjectControls) {
                continue;
            }

            $controlSeparatorId = $standard['controls_seperator'];
            $excelData = Excel::toCollection(collect([]), base_path($standard['controls_path']))->first();
            $rows = [];
            foreach ($excelData as $key => $row) {
                $primaryId = (string)$row[0];
                $subId = (string)$row[1];
                $name = (string)$row[2];
                $description = (string)$row[3];

                if (empty($primaryId) || empty($subId) || empty($name) || empty($description)) {
                    continue;
                }
                
                $rows[] = [
                    'primary_id' => $primaryId,
                    'sub_id' => $subId,
                    'name' => $name,
                    'description' => $description,
                ];
            }
            unset($row);
            $duplicateControlRows = [];


            // Finding if standard csv has duplicate controls id
            foreach ($rows as $index => $row) {
                if ($index == 0) {
                    continue;
                }
                $targetPrimaryId = $row['primary_id'];
                $targetSubId = $row['sub_id'];



                foreach ($rows as $key => $rowItem) {
                    /* skipping the first iteration and also the control on check index */
                    if ($key == 0 || $key == $index ) {
                        continue;
                    }

                    if ($targetPrimaryId  === $rowItem['primary_id'] && $targetSubId === $rowItem['sub_id']) {
                        $duplicateControlRows [] = $index+1;
                    }
                }
            }
            unset($row);



            /* Skipping the update of current standard controls and projects control if csv file has duplicate entries */
            if (!empty($duplicateControlRows)) {
                $logMsg = 'Standard >> '.$matchedStandard->name.' '.$matchedStandard->version.' is skipped because its xlxs file has duplicate controlId(s) in row number '.implode(",", $duplicateControlRows);


                $this->error($logMsg);
                Log::error($logMsg);

                continue;
            }


            foreach ($projects as $key => $project) {
                $projectDataScope = $project->scope;
                DB::beginTransaction();
                $afterUpdateControlIds=[];

                try {

                /*  */
                    foreach ($rows as $controlIndex => $control) {
                        /* Skipping the first iteration */
                        if ($controlIndex == 0) {
                            continue;
                        }
                        $primaryId = $control['primary_id'];
                        $subId = $control['sub_id'];
                        $controlID = $primaryId.$controlSeparatorId.$subId;
                        $controlName = clean($control['name']);
                        $controlDescription = clean($control['description']);
                        $controlUpdateData = [
                            'primary_id' => $primaryId,
                            'id_separator' => $controlSeparatorId,
                            'sub_id' => $subId,
                            'index' => $controlIndex,
                            'name' => $controlName,
                            'description' => $controlDescription
                        ];

                        /* Querying the controls by control ID (PrimaryID + Separator ID + SubID) */
                        $matchedControlsById = $project->controls()->where(function ($query) use ($controlSeparatorId, $primaryId, $subId) {
                            $query->where('primary_id', $primaryId)->where('id_separator', $controlSeparatorId)->where('sub_id', $subId);
                        })->get();

                        $resultCount = $matchedControlsById->count();

                        /* Updates the existing records in compliance_project_controls db table */
                        if ($resultCount ===  1) {
                            $controlToUpdate = $matchedControlsById->first();
                            $controlToUpdate->update($controlUpdateData);

                            $afterUpdateControlIds[] = [
                                'id' => $controlToUpdate->id,
                                'control_id' => $controlID
                            ];

                            /* moving to next iteration */
                            continue;
                        }

                        /* when controlID of current control row from Excel does not have any result or more than one */
                        /* Handling duplicate records in compliance_project_controls db table */
                        /* Finding control through name */
                        $matchedControls = $project->controls()->where(function ($query) use ($controlName) {
                            $query->where('name', $controlName);
                        })->get();

                        $resultCount = $matchedControls->count();

                        if ($resultCount === 1) {
                            $controlToUpdate = $matchedControls->first();
                            $controlToUpdate->update($controlUpdateData);

                            /* Storing controls ids which are */
                            $afterUpdateControlIds[] = [
                                'id' => $controlToUpdate->id,
                                'control_id' => $controlID
                            ];

                            /* moving to next iteration */
                            continue;
                        }

                        /* Finding control by querying description */
                        $matchedControls = $project->controls()->where(function ($query) use ($controlDescription) {
                            $query->where('description', $controlDescription);
                        })->get();
                        $resultCount = $matchedControls->count();

                        if ($resultCount === 1) {
                            $controlToUpdate = $matchedControls->first();
                            $controlToUpdate->update($controlUpdateData);

                            /* Storing controls ids which are */
                            $afterUpdateControlIds[] = [
                                'id' => $controlToUpdate->id,
                                'control_id' => $controlID
                            ];


                            /* moving to next iteration */
                            continue;
                        }

                        /* Handling fully duplicate control */
                        $assignedControls = $matchedControlsById->where('approver', '!==', null)->where('responsible', '!==', null);

                        if ($assignedControls->count() == 0) {
                            /* moving to next iteration */
                            continue;
                        }

                        /*  */
                        $controlToUpdate = $assignedControls->first();
                        $controlToUpdate->update($controlUpdateData);

                        $afterUpdateControlIds[] = [
                            'id' => $controlToUpdate->id,
                            'control_id' => $controlID
                        ];
                    } // End of controls foreach loop

                    $existingControlIds = collect($afterUpdateControlIds)->pluck('control_id')->toArray();
                    $notToDeleteControlIds = collect($afterUpdateControlIds)->pluck('id')->toArray();
                    /* Finding missing controls from projects */
                    foreach ($rows as $controlIndex => $control) {
                        /* Skipping the first iteration */
                        if ($controlIndex == 0) {
                            continue;
                        }
                        

                        $controlIdToCheck = $control['primary_id'].$controlSeparatorId.$control['sub_id'];
                        if (!in_array($controlIdToCheck, $existingControlIds)) {
                            Log::info('missing control:'.$controlIdToCheck.'Standard:'.$matchedStandard->id);
                            $primaryId = $control['primary_id'];
                            $subId = $control['sub_id'];
                            $controlID = $primaryId.$controlSeparatorId.$subId;
                            $controlName = clean($control['name']);
                            $controlDescription = clean($control['description']);
                            $newControl = $project->controls()->create([
                                'primary_id' => $primaryId,
                                'id_separator' => $controlSeparatorId,
                                'sub_id' => $subId,
                                'index' => $controlIndex,
                                'name' => $controlName,
                                'description' => $controlDescription
                            ]);


                            // Scopable
                            $controlDataScope = [
                                'organization_id' => $projectDataScope->organization_id,
                                'department_id'=> $projectDataScope->department_id,
                                'scopable_id'=> $newControl->id,
                                'scopable_type'=> 'App\Models\Compliance\ProjectControl'
                            ];

                            Scopable::insert($controlDataScope);

                            $notToDeleteControlIds[] = $newControl->id;

                            continue;
                        }
                    }
                    /* Deleting the controls from db table that are not exist in new csv file */
                    $controlsToBeDeletedQuery = ProjectControl::where(function ($query) use ($notToDeleteControlIds, $project) {
                        $query->where('project_id', $project->id);
                        $query->whereNotIn('id', $notToDeleteControlIds);
                    })->with(['evidences', 'comments', 'justifications', 'risks']);

                    $controlsToBeDeleted = $controlsToBeDeletedQuery->get();
                    $controlsToBeBackup = [];
                    $controlEvidencesToBackup = [];
                    $controlCommentsToBackup = [];
                    $controlJustificationToBackup = [];
                    $controlMappedRiskToBackup = [];
                    $dataScopeToBackup = [];
                    $scopableDataToDelete = [];

                    foreach ($controlsToBeDeleted as $key => $control) {
                        $controlDataScope = $control->scope;
                        $scopableDataToDelete[] = $controlDataScope->id;
                        $dataScopeToBackup[] = [
                            "id" => $controlDataScope->id,
                            "organization_id" => $controlDataScope->organization_id,
                            "department_id" => $controlDataScope->department_id,
                            "scopable_id" =>  $controlDataScope->scopable_id,
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

                        /* Creating mapped risks data to backup  */
                        foreach ($control->risks as $key => $risk) {
                            $controlMappedRiskToBackup[] = [
                                "id" => $risk->pivot->id,
                                "control_id" => $risk->pivot->control_id,
                                "risk_id" => $risk->pivot->risk_id,
                                "created_at" => $risk->pivot->created_at,
                                "updated_at" => $risk->pivot->updated_at,
                            ];
                        }

                        /* Creating evidences data to backup  */
                        foreach($control->evidences as $evidence){
                            $controlEvidencesToBackup = [
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
                        foreach($control->comments as $comment){
                            $controlCommentsToBackup[] =  [
                                "id" => $comment->id,
                                "project_control_id" => $comment->project_control_id,
                                "from" => $comment->from,
                                "to" => $comment->to,
                                "comment" => $comment->comment,
                                "created_at" => $comment->created_at,
                                "updated_at" => $comment->updated_at,
                            ];
                        }

                            /* Creating justifications data to backup  */
                        foreach ($control->justifications as $key => $justification) {
                            $controlJustificationToBackup[] = [
                                "id" => $justification->id,
                                "project_control_id" => $justification->project_control_id,
                                "justification" => $justification->justification,
                                "for" => $justification->for,
                                "creator_id" =>  $justification->creator_id,
                                "created_at" => $justification->created_at,
                                "updated_at" => $justification->updated_at,
                            ];
                        }
                    }

                    /* Backup the control mapped risk */
                    if (!empty($controlMappedRiskToBackup)) {
                        RiskMappedComplianceControlBackup::insert($controlMappedRiskToBackup);
                    }

                    /* Making backup for controls */
                    if (!empty($controlsToBeBackup)) {
                        ProjectControlBackup::insert($controlsToBeBackup);
                    }

                    /* Making backup for control evidences */
                    if (!empty($controlEvidencesToBackup)) {
                        EvidenceBackup::insert($controlEvidencesToBackup);
                    }

                    /* Making backup for control comments */
                    if (!empty($controlCommentsToBackup)) {
                        CommentBackup::insert($controlCommentsToBackup);
                    }

                    /* Making backup for control comments */
                    if (!empty($controlJustificationToBackup)) {
                        JustificationBackup::insert($controlJustificationToBackup);
                    }

                    /* Making backup for control data scope */
                    if (!empty($dataScopeToBackup)) {
                        ScopableBackup::insert($dataScopeToBackup);
                    }

                    /* Deleting the control data */
                    $controlsToBeDeletedQuery->delete();
                    /* deleting the scopable data */
                    Scopable::whereIn('id', $scopableDataToDelete)->delete();

                    DB::commit();


                    $logMsg = 'Controls of Project `'.$project->name.'`'.' has been updated successfully!';
                    $this->info($logMsg);
                    Log::info($logMsg);
                    // all good
                } catch (\Exception $e) {
                    DB::rollback();

                    $logMsg = 'Controls of Project `'.$project->name.'`'.' not able to update!';
                    $this->error($logMsg);
                    Log::error($logMsg);
                    Log::error($e->getMessage());
                    Log::error($e->getFile());
                    Log::error($e->getLine());
                }
            } // project loop end
            unset($project);
        }
        unset($standard);


        $this->newLine();
        $logMsg = 'Standard(s) and their associated project(s) controls are updated!';
        $this->info($logMsg);
    }
}