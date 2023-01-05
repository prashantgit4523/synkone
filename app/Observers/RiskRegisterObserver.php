<?php

namespace App\Observers;

use App\Models\RiskManagement\RiskMatrix\RiskMatrixImpact;
use App\Models\RiskManagement\RiskMatrix\RiskMatrixLikelihood;
use App\Models\RiskManagement\RiskMappedComplianceControl;
use App\Models\RiskManagement\RiskRegister;
use App\Models\RiskManagement\RiskRegisterHistoryLog;
use App\Models\RiskManagement\RiskMatrix\RiskMatrixScore;
use App\Models\GlobalSettings\GlobalSetting;
use App\Models\RiskManagement\RiskMatrix\RiskMatrixAcceptableScore;
use App\Models\RiskManagement\RiskNotification;

class RiskRegisterObserver
{
    public function creating(RiskRegister $RiskRegister)
    {
        $likelihoodCount = RiskMatrixLikelihood::count();
        $impactCount = RiskMatrixImpact::count();

        $is_3x3 = ($likelihoodCount === $impactCount) && ($likelihoodCount === 3);

        /* Setting the default value whe not set*/
        if (!isset($RiskRegister->likelihood)) {
            $middleLikelihood = $is_3x3 ? 2 : intval(floor($likelihoodCount / 2));
            $RiskRegister->likelihood = $middleLikelihood;
        }

        if (!isset($RiskRegister->impact)) {
            $middleImpact = $is_3x3 ? 2 : intval(floor($impactCount / 2));
            $RiskRegister->impact = $middleImpact;
        }

        /* When likelihood  and impact index is given*/
        if (isset($RiskRegister->likelihood) && isset($RiskRegister->impact)) {
            $riskScore = RiskMatrixScore::where('likelihood_index', $RiskRegister->likelihood - 1)->where('impact_index', $RiskRegister->impact - 1)->first();

            if ($riskScore) {
                $RiskRegister->inherent_score = $riskScore->score;
                $RiskRegister->residual_score = $riskScore->score;
            }
        }
    }

    public function created(RiskRegister $RiskRegister)
    {
        $globalSettings = GlobalSetting::first();
        $nowDateTime = new \DateTime('now', new \DateTimeZone($globalSettings->timezone));
        $todayDate = $nowDateTime->format('Y-m-d');

        $risk = RiskRegister::findOrFail($RiskRegister->id);

        $changeLogData = [
            'project_id' => $risk->project_id,
            'risk_register_id' => $risk->id,
            'category_id' => $risk->category_id,
            'log_date' => $todayDate,
            'risk_created_date' => $todayDate,
            'status' => $risk->status,
            'likelihood' => $risk->likelihood,
            'impact' => $risk->impact,
            'inherent_score' => $risk->inherent_score,
            'residual_score' => $risk->residual_score,
            'is_complete' => is_null($risk->is_complete) ? '0' : $risk->is_complete
        ];

        RiskRegisterHistoryLog::create($changeLogData);
    }

    /**
     * Handle the Risk control "updating" event.
     *
     * @param \App\Models\Admin\RiskRegister $RiskRegister
     */
    public function updating(RiskRegister $RiskRegister)
    {
        //For accepted risk change status to close and removing all mapped project controls
        if (($RiskRegister->isDirty('treatment_options') && $RiskRegister->treatment_options == 'Accept')
            ||
            ($RiskRegister->isDirty('residual_score') && $RiskRegister->residual_score <= RiskMatrixAcceptableScore::first()->score)
        ) {
            $RiskRegister->status = 'Close';

            if ($RiskRegister->treatment_options == 'Accept') {
                RiskMappedComplianceControl::where('risk_id', $RiskRegister->id)->delete();
            }
        }

        //For Mitigate risk change status to open
        if ($RiskRegister->isDirty('treatment_options') && $RiskRegister->treatment_options == 'Mitigate') {
            $RiskRegister->status = 'Open';
        }

        if ($RiskRegister->status === 'Close') {
            RiskNotification::firstOrCreate(['risk_id' => $RiskRegister->id]);
        }

        $risk = RiskRegister::with('controls')->find($RiskRegister->id);

        if ($risk->controls->first() && $risk->controls->first()['status'] === 'Implemented') {
            $RiskRegister->status = 'Close';
            if ($RiskRegister->inherent_score > RiskMatrixAcceptableScore::first()->score) {
                $RiskRegister->residual_score = RiskMatrixAcceptableScore::first()->score;
            }
            if ($RiskRegister->inherent_score <= RiskMatrixAcceptableScore::first()->score) {
                $RiskRegister->residual_score = $RiskRegister->inherent_score;
            }
        } elseif ($RiskRegister->inherent_score <= RiskMatrixAcceptableScore::first()->score) {
            $RiskRegister->status = 'Close';
            $RiskRegister->residual_score = $RiskRegister->inherent_score;
        } else {
            $RiskRegister->status = 'Open';
            $RiskRegister->residual_score = $RiskRegister->inherent_score;
        }
    }

    public function updated(RiskRegister $RiskRegister)
    {
        /* SAVING THE Risk Register CHANGE LOG */
        if ($RiskRegister->isDirty('category_id') || $RiskRegister->isDirty('likelihood') || $RiskRegister->isDirty('impact') || $RiskRegister->isDirty('inherent_score') || $RiskRegister->isDirty('residual_score') || $RiskRegister->isDirty('status')) {

            $globalSettings = GlobalSetting::first();
            $nowDateTime = new \DateTime('now', new \DateTimeZone($globalSettings->timezone));
            $todayDate = $nowDateTime->format('Y-m-d');

            $riskChangeLog = RiskRegisterHistoryLog::where('log_date', $todayDate)->where('risk_register_id', $RiskRegister->id)->first();

            $changeLogData = [
                'project_id' => $RiskRegister->project_id,
                'risk_register_id' => $RiskRegister->id,
                'category_id' => $RiskRegister->category_id,
                'log_date' => $todayDate,
                'risk_created_date' => $RiskRegister->created_at,
                'risk_deleted_date' => $RiskRegister->deleted_at,
                'status' => $RiskRegister->status,
                'likelihood' => $RiskRegister->likelihood,
                'impact' => $RiskRegister->impact,
                'inherent_score' => $RiskRegister->inherent_score,
                'residual_score' => $RiskRegister->residual_score,
                'is_complete' => is_null($RiskRegister->is_complete) ? '0' : $RiskRegister->is_complete
            ];

            $currentDay = date('Y-m-d H:i:s');
            if (!is_null($riskChangeLog)) {
                $changeLogData['updated_at'] = $currentDay;
                $riskChangeLog->update($changeLogData);
            } else {
                $changeLogData['created_at'] = $currentDay;
                $changeLogData['updated_at'] = $currentDay;
                RiskRegisterHistoryLog::create($changeLogData);
            }
        }
    }

    public function deleted(RiskRegister $RiskRegister)
    {
        $globalSettings = GlobalSetting::first();
        $nowDateTime = new \DateTime('now', new \DateTimeZone($globalSettings->timezone));
        $todayDate = $nowDateTime->format('Y-m-d');

        $riskChangeLog = RiskRegisterHistoryLog::where('log_date', $todayDate)->where('risk_register_id', $RiskRegister->id)->first();
        $changeLogData = [
            'risk_deleted_date' => $todayDate
        ];

        if (!is_null($riskChangeLog)) {
            $riskChangeLog->update($changeLogData);
        } else {
            $changeLogData['project_id'] = $RiskRegister->project_id;
            $changeLogData['risk_register_id'] = $RiskRegister->id;
            $changeLogData['category_id'] = $RiskRegister->category_id;
            $changeLogData['log_date'] = $todayDate;
            $changeLogData['risk_created_date'] = $RiskRegister->created_at;
            $changeLogData['status'] = $RiskRegister->status;
            $changeLogData['likelihood'] = $RiskRegister->likelihood;
            $changeLogData['impact'] = $RiskRegister->impact;
            $changeLogData['inherent_score'] = $RiskRegister->inherent_score;
            $changeLogData['residual_score'] = $RiskRegister->residual_score;
            $changeLogData['is_complete'] = is_null($RiskRegister->is_complete) ? '0' : $RiskRegister->is_complete;
            RiskRegisterHistoryLog::create($changeLogData);
        }
    }
}
