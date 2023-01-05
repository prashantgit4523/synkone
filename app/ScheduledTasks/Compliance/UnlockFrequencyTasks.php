<?php

namespace App\ScheduledTasks\Compliance;

use App\Models\PolicyManagement\Policy;
use Carbon\Carbon;
use App\Models\Compliance\ProjectControl;
use App\ScheduledTasks\TenantScheduleTrait;
use App\Models\TaskScheduleRecord\ComplianceProjectTaskScheduleRecord;

class UnlockFrequencyTasks
{
    use TenantScheduleTrait;

    public function __invoke()
    {
        if(tenant('id')){
            $expired=$this->checkIfSubscriptionExpired(tenant('id'));
            if($expired){
                return false;
            }
            $this->SetUpTenantMailContent(tenant('id'));
        }
        $unlockingDate = date('Y-m-d', strtotime('+14 days'));

        //Getting today save task schedule name from compliance project task schedule record database
        $taskScheduleRecord = ComplianceProjectTaskScheduleRecord::whereDate('created_at', Carbon::today()->toDateString())->where('name', 'unlockFerquency')->pluck('compliance_project_control_id')->toArray();

        $applicableProjectControls = ProjectControl::where('applicable', 1)
                                        ->whereNotNull('responsible')
                                                ->whereNotNull('frequency')
                                                    ->where('frequency', '!=', 'One-Time')
                                                        ->where('current_cycle', '!=', 1)
                                                            ->get();

        foreach ($applicableProjectControls as $key => $applicableProjectControl) {
            //checking single unlockfrequency run
            if (!in_array($applicableProjectControl->id, $taskScheduleRecord)) {
                $currentDeadline = strtotime($applicableProjectControl->deadline);

                $frequency = $applicableProjectControl->frequency;

                switch ($frequency) {
                case 'Monthly':
                $nextReviewDate = date('Y-m-d', strtotime('+1 month', $currentDeadline));
                    break;
                case 'Every 3 Months':
                $nextReviewDate = date('Y-m-d', strtotime('+3 month', $currentDeadline));
                    break;
                case 'Bi-Annually':
                $nextReviewDate = date('Y-m-d', strtotime('+6 month', $currentDeadline));
                    break;
                case 'Annually':
                $nextReviewDate = date('Y-m-d', strtotime('+1 years', $currentDeadline));
                    break;
            }

                if ($unlockingDate == $nextReviewDate) {
                    $applicableProjectControl->is_editable = 1;
                    $applicableProjectControl->status = 'Not Implemented';
                    $applicableProjectControl->approved_at = null;
                    $applicableProjectControl->unlocked_at = date('Y-m-d');
                    if ($applicableProjectControl->evidencesUploadStatus) {
                        $applicableProjectControl->evidencesUploadStatus->update([
                        'status' => 1,
                    ]);
                    }

                    if(
                        $applicableProjectControl->automation === 'document'
                        && $applicableProjectControl->template->versions->last()->status === 'published'
                    ){
                        $document = $applicableProjectControl->template->versions->last();
                        $applicableProjectControl->status = 'Implemented';
                        $new_version = intval($document->version) + 1;
                        $new_control_document = $document->replicate();
                        $new_control_document->version = $new_version . '.0';
                        $new_control_document->description = 'Yearly review, no changes';
                        $new_control_document->save();
                        //scope
                        $scope = $document->scope->replicate();
                        $scope->scopable_id = $new_control_document->id;
                        $scope->save();
                        //
                        $policy = Policy::query()->where('path', $document->template->id)->firstOrFail();
                        $policy->update([
                            'description' => $new_control_document->description,
                            'version' => $new_control_document->version
                        ]);
                    }

                    $applicableProjectControl->deadline = $nextReviewDate;

                    $applicableProjectControl->update();
                }

                $todayComplianceProjectTaskScheduleUnlockFrequency = new ComplianceProjectTaskScheduleRecord();
                $todayComplianceProjectTaskScheduleUnlockFrequency->compliance_project_control_id = $applicableProjectControl->id;
                $todayComplianceProjectTaskScheduleUnlockFrequency->name = 'unlockFerquency';
                $todayComplianceProjectTaskScheduleUnlockFrequency->save();
            }
        }
    }
}
