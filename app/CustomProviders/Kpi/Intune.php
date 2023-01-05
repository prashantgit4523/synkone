<?php

namespace App\CustomProviders\Kpi;

use Illuminate\Support\Facades\Http;
use App\CustomProviders\CustomProvider;
use App\Traits\Kpi\KpiIntegrationTrait;
use App\Traits\Integration\IntegrationApiTrait;
use App\CustomProviders\Interfaces\IDeviceManagement;
use App\CustomProviders\Interfaces\IHaveHowToImplement;

class Intune extends CustomProvider implements IDeviceManagement, IHaveHowToImplement
{

    use IntegrationApiTrait,KpiIntegrationTrait;

    public $total_enabled_users_count = 0;
    public $total_enabled_devices_count = 0;

    public function __construct()
    {
        parent::__construct('intune', 'https://login.microsoftonline.com/common/oauth2/v2.0/token');
        // get total users
        $total_active_users_response = Http::withToken($this->provider->accessToken)
            ->get('https://graph.microsoft.com/beta/users', [
                '$select' => 'id,displayName',
                'filter' => 'accountEnabled eq ' . 'true',
            ]);
        
        $total_active_users = json_decode($total_active_users_response->body(), true);
        
        if(array_key_exists('value',$total_active_users)){
            $this->total_enabled_users_count = count($total_active_users['value']);

            $total_active_devices_response = Http::withToken($this->provider->accessToken)
                ->get('https://graph.microsoft.com/v1.0/devices?$count=true');
            $total_active_devices = json_decode($total_active_devices_response->body(), true);
            foreach ($total_active_devices['value'] as $value) {
                if ($value['operatingSystem'] == 'Windows' && str_contains($value['operatingSystemVersion'], '10')) {
                    $this->total_enabled_devices_count++;
                }
            }
        }

        
    }

    public function getBlockedUsbStatus(): ?string
    {
        try {
            return $this->handleCustomPolicyKpi('UsbBlockingCompliant');
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with getBlockedUsbStatus on Intune');
        }
        return null;
    }

    public function getPasswordComplexityStatus(): ?string
    {
        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get('https://graph.microsoft.com/beta/devicemanagement/deviceConfigurations', [
                    '$expand' => 'Assignments',
                ]);

            $data['total'] = $this->total_enabled_devices_count;
            $data['passed'] = 0;

            $group_ids=[];
            if ($response->ok()) {
                $body = json_decode($response->body(), true);

                $apiResponse = $body['value'];
                if (count($apiResponse) > 0) {
                    foreach ($apiResponse as $value) {
                        if(array_key_exists('passwordMinimumLength', $value) && 
                           array_key_exists('passwordMinimumCharacterSetCount', $value) && 
                           array_key_exists('assignments', $value) && 
                           $value['passwordMinimumLength'] && 
                           $value['passwordMinimumCharacterSetCount']
                        ){
                            if($value['passwordMinimumLength'] >=8 && $value['passwordMinimumCharacterSetCount'] >=3 ){
                                foreach($value['assignments'] as $assignment){
                                    $group_id=$assignment['target']['groupId'];
                                    array_push($group_ids,$group_id);
                                }
                            }
                        }

                    }
                }
                if(count($group_ids) > 0 ){
                    $devices=$this->getUniqueDevicesFromGroups($group_ids,$this->provider->accessToken);
                    $data['passed'] = count($devices);
                    return json_encode($data);
                }
            }
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with getPasswordComplexityStatus on Intune');
        }

        return null;
    }

    public function getConditionalAccessStatus(): ?string
    {
        return null;
    }

    
    // condition: check if BitLockerEncryption is enabled or not
    // Standard: ISO 27001-2-2013
    // control : A.6.2.1
    public function getMobileDeviceStatus(): ?string
    {
        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get('https://graph.microsoft.com/beta/deviceManagement/managedDevices?$select=complianceState');
                
            if ($response->ok()) {
                $body = json_decode($response->body(), true);
                
                $data['total'] = $body['@odata.count'];
                $data['passed'] = 0;

                $apiResponse = $body['value'];
                
                if ($body['@odata.count'] > 0) {
                    foreach ($apiResponse as $value) {
                        if ($value["complianceState"] === "compliant") {
                            $data['passed']++;
                        }

                    }
               
                    return json_encode($data);
                }
            }
        } catch (\Exception $e) {
            echo $e;
        }
    }

    
    // condition: check if BitLockerEncryption is enabled or not
    // Standard: ISO 27001-2-2013
    // control : A.10.1.1
    public function getHddEncryptionStatus(): ?string
    {
        try {
            return $this->handleCustomPolicyKpi('BitLockerEncryptionCompliant');
        } catch (\Exception $e) {
            return $e->getMessage();
        }
        return null;
    }

    public function getInactivityStatus(): ?string
    {
        try {
            return $this->handleCustomPolicyKpi('InactivityPolicyCompliant');
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with getInactivityStatus on Intune');
        }
        return null;
    }

    public function getAntivirusStatus(): ?string
    {
        try {
            return $this->handleCustomPolicyKpi('CompliantActiveAntiVirus');
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with getAntivirusStatus on Intune');
        }
        return null;
    }

    public function getNtpStatus(): ?string
    {
        try {
            return $this->handleCustomPolicyKpi('PolicyCompliantStatus');
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with getNtpStatus on Intune');
        }
        return null;
    }

    public function getLocalAdminRestrictStatus(): ?string
    {
        try {
            return $this->handleCustomPolicyKpi('CompliantActiveAntiVirus');
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with getLocalAdminRestrictStatus on Intune');
        }
        return null;
    }

    //checks for autoupdates
    public function getTechVulnerabilitiesScanStatus(): ?string
    {
        try {
            return $this->handleCustomPolicyKpi('CompliantAutoUpdates');
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with getTechVulnerabilitiesScanStatus on Intune');
        }
        return null;
    }

    public static function getHowToImplementAction($action): ?string
    {
        $howToImplementActionsArr = [
            "getBlockedUsbStatus" => "https://docs.microsoft.com/en-us/troubleshoot/mem/intune/restrict-usb-with-administrative-template",
            "getPasswordComplexityStatus" => "https://docs.microsoft.com/en-us/mem/intune/protect/quickstart-set-password-length-android",
            "getConditionalAccessStatus" => "https://docs.microsoft.com/en-us/azure/active-directory/authentication/tutorial-enable-azure-mfa?bc=/azure/active-directory/conditional-access/breadcrumb/toc.json&toc=/azure/active-directory/conditional-access/toc.json#create-a-conditional-access-policy",
            "getMobileDeviceStatus" => "https://docs.microsoft.com/en-us/mem/intune/protect/create-compliance-policy",
            "getHddEncryptionStatus" => "https://petri.com/best-practices-for-deploying-bitlocker-with-intune",
            "getInactivityStatus" => "https://www.anoopcnair.com/set-automatic-lock-screen-for-inactive-device-intune/",
            "getAntivirusStatus" => "https://docs.microsoft.com/en-us/mem/intune/protect/endpoint-security-antivirus-policy",
            "getNtpStatus" => "https://www.anoopcnair.com/how-to-set-time-zone-using-intune-mem-avd-vms/",
            "getTechVulnerabilitiesScanStatus" => "https://4sysops.com/archives/managing-windows-updates-with-microsoft-intune/",
        ];

        return array_key_exists($action, $howToImplementActionsArr) ? $howToImplementActionsArr[$action] : null;
    }
}
