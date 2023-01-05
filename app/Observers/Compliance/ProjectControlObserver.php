<?php

namespace App\Observers\Compliance;

use App\Models\Compliance\ProjectControl;
use App\Models\RiskManagement\RiskMatrix\RiskMatrixAcceptableScore;
use App\Models\Compliance\ComplianceProjectControlHistoryLog;
use App\Utils\RegularFunctions;

class ProjectControlObserver
{
    private const DATETIME_FORMAT = 'Y-m-d H:i:s';

    /**
     * Handle the project control "updating" event.
     *
     * @param \App\Models\Admin\ProjectControl $projectControl
     */
    public function updating(ProjectControl $projectControl)
    {
    }

    /**
     * Handle the project control "updated" event.
     *
     * @param \App\Models\Admin\ProjectControl $projectControl
     */
    public function updated(ProjectControl $projectControl)
    {
        $mappedRisks = $projectControl->risks()->get();

        if ($projectControl->isDirty('status') && $mappedRisks->count() > 0) {

            if ($projectControl->status == 'Implemented') {
                /* Setting residual risk score to acceptable score */
                foreach ($mappedRisks as $mappedRisk) {
                    $riskAcceptableScore = RiskMatrixAcceptableScore::first();
                    $mappedRisk->status = 'Close';
                    $mappedRisk->residual_score = $riskAcceptableScore->score;
                    $mappedRisk->update();
                }
            } elseif ($projectControl->status == 'Not Implemented') {
                foreach ($mappedRisks as $mappedRisk) {
                    $mappedRisk->status = 'Open';
                    $mappedRisk->treatment_options = 'Mitigate';
                    $mappedRisk->residual_score = $mappedRisk->inherent_score;
                    $mappedRisk->update();
                }
            }
        }


        // Mirroring child controls with parent controls
        if (count($projectControl->childLinkedControls())) {
            foreach($projectControl->childLinkedControls() as $childProjectControl){
                $childProjectControl->update([
                    'status' => $projectControl->status,
                    'is_editable'=> $projectControl->automation === 'technical' && $projectControl->status === 'Implemented' ? 0 : $projectControl->is_editable
                ]);
                if($projectControl->status=='Implemented'){
                    $childProjectControl->update([
                        'approved_at'=>$projectControl->approved_at
                    ]);
                }
            }
        }


        /* SAVING THE PROJECT COTNROL CHANGE LOG */
        if($projectControl->isDirty('applicable') || $projectControl->isDirty('status') || $projectControl->isDirty('deadline') || $projectControl->isDirty('frequency')){
            $todayDate = RegularFunctions::getTodayDate();
            $controlChangeLog = ComplianceProjectControlHistoryLog::where('log_date', $todayDate)->where('control_id', $projectControl->id)->first();

            $changeLogData = [
                'project_id' => $projectControl->project_id,
                'control_id' => $projectControl->id,
                'applicable' => $projectControl->applicable,
                'log_date' => $todayDate,
                'control_created_date' => $projectControl->created_at,
                'status' => $projectControl->status,
                'deadline' => $projectControl->deadline,
                'frequency' => $projectControl->frequency
            ];
            if (!is_null($controlChangeLog)) {
                $changeLogData['updated_at'] = date(self::DATETIME_FORMAT);
                $controlChangeLog->update($changeLogData);
            } else {
                $changeLogData['created_at'] = date(self::DATETIME_FORMAT);
                $changeLogData['updated_at'] = date(self::DATETIME_FORMAT);
                ComplianceProjectControlHistoryLog::create($changeLogData);
            }
        }

    }

    /**
     * Handle the project control "restored" event.
     *
     * @param \App\Models\Admin\ProjectControl $projectControl
     */
    public function restored(ProjectControl $projectControl)
    {
    }

    /**
     * Handle the project control "force deleted" event.
     *
     * @param \App\Models\Admin\ProjectControl $projectControl
     */
    public function forceDeleted(ProjectControl $projectControl)
    {
    }
}
