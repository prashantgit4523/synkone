<?php

namespace App\Observers;

use App\Utils\RegularFunctions;
use App\Models\ThirdPartyRisk\Vendor;
use App\Models\ThirdPartyRisk\Project\ProjectVendor;
use App\Models\ThirdPartyRisk\VendorHistoryLog;
use App\Models\GlobalSettings\GlobalSetting;

class ThirdPartyVendorObserver
{

    
    public function created(Vendor $Vendor)
    {
        $changeLogData = [
            'third_party_vendor_id' => $Vendor->id,
            'status' => 'active',
            'score' => '0',
            'log_date' => $Vendor->created_at->format('Y-m-d'),
            'vendor_created_date' => $Vendor->created_at,
            'vendor_deleted_date' => $Vendor->deleted_at,
            'created_at' => $Vendor->created_at,
            'updated_at' => $Vendor->updated_at
        ];
        VendorHistoryLog::create($changeLogData);
    }

    
    public function updated(Vendor $Vendor)
    {
        \Log::info('Third Party Vendor Updated');
        /* SAVING THE Risk Register CHANGE LOG */
        if($Vendor->isDirty('status') || $Vendor->isDirty('score')){
            $globalSettings = GlobalSetting::first();
            $nowDateTime = new \DateTime('now', new \DateTimeZone($globalSettings->timezone));
            $todayDate = $nowDateTime->format('Y-m-d');

            \Log::info('Third Party Vendor Updated Today: '.$todayDate);
            \Log::info('Third Party Vendor Score is Changed to: '.$Vendor->score);

            $vendorChangeLog = VendorHistoryLog::where('log_date', $todayDate)->where('third_party_vendor_id', $Vendor->id)->first();
            \Log::info('Third Party Vendor Previous History: '.json_encode($vendorChangeLog));

            if (!is_null($vendorChangeLog)) {
                $changeLogData = [
                    'status' => $Vendor->status,
                    'score' => $Vendor->score
                ];
                \Log::info('Third Party Vendor Update Data: '.json_encode($changeLogData));
                $vendorChangeLog->update($changeLogData);
            } else {
                $changeLogData = [
                    'status' => $Vendor->status,
                    'score' => $Vendor->score,
                    'third_party_vendor_id' => $Vendor->id,
                    'log_date' => $todayDate,
                    'vendor_created_date' => $todayDate,
                    'vendor_deleted_date' => $Vendor->deleted_at,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ];
                \Log::info('Third Party Vendor New Data: '.json_encode($changeLogData));
                VendorHistoryLog::create($changeLogData);
            }
        }

        if($Vendor->isDirty('name') || $Vendor->isDirty('contact_name') || $Vendor->isDirty('email') || $Vendor->isDirty('country') || $Vendor->isDirty('industry_id'))
        {
            $projectVendor = ProjectVendor::where('vendor_id', $Vendor->id)->first();

            if (!is_null($projectVendor)) {
                $changeLogData = [
                    'name' => $Vendor->name,
                    'contact_name' => $Vendor->contact_name,
                    'email' => $Vendor->email,
                    'country' => $Vendor->country,
                    'industry_id' => $Vendor->industry_id
                ];

                \DB::table('third_party_project_vendors')->where('vendor_id', $Vendor->id)->update($changeLogData);
            }
        }
    }

    public function deleted(Vendor $Vendor)
    {
        $globalSettings = GlobalSetting::first();
        $nowDateTime = new \DateTime('now', new \DateTimeZone($globalSettings->timezone));
        $todayDate = $nowDateTime->format('Y-m-d');

        $vendorChangeLog = VendorHistoryLog::where('log_date', $todayDate)->where('third_party_vendor_id', $Vendor->id)->first();
        $changeLogData = [
            'vendor_deleted_date' => $todayDate
        ];

        if (!is_null($vendorChangeLog)) {
            $vendorChangeLog->update($changeLogData);
        } else {
            $changeLogData['third_party_vendor_id'] = $Vendor->id;
            $changeLogData['status'] = $Vendor->status;
            $changeLogData['score'] = $Vendor->score;
            $changeLogData['log_date'] = $todayDate;
            $changeLogData['vendor_created_date'] = $todayDate;
            VendorHistoryLog::create($changeLogData);
        }
    }
}