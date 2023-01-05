<?php

namespace App\CustomProviders;

use App\CustomProviders\Interfaces\IDeviceManagement;
use App\CustomProviders\Interfaces\IHaveHowToImplement;
use App\Traits\Integration\IntegrationApiTrait;
use App\Traits\Integration\JumpCloudApiTrait;
use App\Traits\Kpi\jumpCloudKpiTrait;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
class JumpCloud extends CustomProvider implements IHaveHowToImplement,IDeviceManagement
{
    private PendingRequest $client;
    use IntegrationApiTrait;
    use JumpCloudApiTrait;
    use jumpCloudKpiTrait;

    // note:
    //here we integrate with API KEY found in profileLogo > API Key
    // In jumpCLoud there are policies differentiated by OS
    // And as such, policies from one OS can not be applied to another
    // Thus, when taking Total for KPI or To check implementation level we need to take devices
    // from each OS that can have the policy applied to it whether the policy is created/Implemented
    public function __construct()
    {
        parent::__construct('jumpCloud');
        $this->api_key = $this->fields['api_key'];
        $this->client = Http::withHeaders([
            'x-api-key' => $this->api_key,
        ])
            ->timeout(10)
            ->baseUrl('https://console.jumpcloud.com/api');
        $this->policyResult = $this->getAllDevicesWithPolicies();
    }

    public function attempt(array $fields): bool
    {
        if (
            Http::timeout(5)
            ->withHeaders(['x-api-key' => $fields['api_key']])
            ->baseUrl('https://console.jumpcloud.com')
            ->get('/api/v2/policies')
            ->failed()) {
            return false;
        }

        $this->connect($this->provider, $fields);
        return true;
    }

    // one policy created by admin
    // no mapping in standard mappings
    // how to implement: 'https://support.jumpcloud.com/support/s/article/Basic-Policy-Procedures'
    public function adminPolicySetup(): ?string
    {
       return $this->getOneCreatedPolicy();
    }

    // one active policy that has been implemented
    // Mapping in standard mappings  is "A.6.2.1"  standard ISO 27001-2-2013
    //  how to implement:'https://support.jumpcloud.com/support/s/article/Basic-Policy-Procedures'
    public function getMobileDeviceStatus(): ?string
    {
        return $this->getOneImplementedPolicy();
    }

    // blocked USB storage
    // here jumpCLoud has 2 policies for external storage(for mac and windows)
    // and configurationNames is the name of the condition required to be true
    // get policyData take 3 params
    //     1. policyName
    //     2. configurationName
    //     3. complianceCondition(where the configurationsPassed must be true)
    // Standard mapping is "A.8.3.1" standard ISO 27001-2-2013
    //  how to implement:'https://support.jumpcloud.com/support/s/article/Manage-Removable-Storage-Using-a-Policy'
    // implementation rate > 50 for control to be implemented
    public function getBlockedUsbStatus(): ?string
    {
        $policyNames = [
            '5ccb6247232e1105bf44e854',
            '5e56e0d41f24753d06ae449a',
        ];
        $configurationNames = [
            'RemovableStorageClasses_DenyAll_Access_2',
            'externalHardDrives',
        ];
        $complianceCondition = 'configTrue';
        return $this->getPolicyData($policyNames,$complianceCondition,$configurationNames);
    }

    // device inactivity policy
    // when device is inactive for a certain time,the device should lock
    // jumpCloud has 3 policies for this policy
    // configurationName is timeout in seconds
    // compliance Condition is Inactive(which we test if the time-out is more or equal to 15 mais)
    // Standard mapping is "A.11.2.8" standard ISO 27001-2-2013
    //  how to implement:'https://support.jumpcloud.com/support/s/article/Lock-Inactive-Screens-Using-a-Policy'
    // implementation rate > 50 for control to be implemented
    public function getInactivityStatus(): ?string
    {
        $policyNames =[
            '59b6ee62232e1123015d70b2',
            '599b2bad232e117f7dbaac08',
            '619bfad51f2475277753697a',
        ];
        $configurationNames = [
            'timeout'
        ];
        $complianceCondition = 'Inactivity';
        return $this->getPolicyData($policyNames,$complianceCondition,$configurationNames);
    }

    // device encryption policy(Bit locker)
    // here jumpCloud only has one policy for Windows device encryption
    // configurationName is the name of the condition required to be true
    // this has no configurationName
    // Standard mapping is "A.10.1.1" standard ISO 27001-2-2013
    //  how to implement:'https://support.jumpcloud.com/support/s/article/bitlocker-policy'
    // implementation rate > 50 for control to be implemented
    public function getHddEncryptionStatus(): ?string
    {
        $policyNames =[
            '5beca66c232e1104b9d7d4cb',
        ];
        $complianceCondition = 'enabled';
        return $this->getPolicyData($policyNames,$complianceCondition,false);
    }

    // check if auto update is enabled
    // there is only one policy for this in windows
    // and there are 3 configurations options for this policy(one needs to be true)
    // Standard mapping is "not found" standard ISO 27001-2-2013
    //  how to implement:https://support.jumpcloud.com/support/s/article/deploying-windows-updates-to-your-systems-with-windows-update-policy-2019-08-21-10-36-47'
    // implementation rate > 50 for control to be implemented
    public function getTechVulnerabilitiesScanStatus(): ?string
    {
        $policyNames =[
            '5c1be3d4232e1109e84f625d'
        ];
        $configurationNames =[
            'AUTO_INSTALL_UPDATES',
            'AUTO_INSTALL_MINOR_UPDATES',
            'AUTO_INSTALL_DURING_MAINT'
        ];
        $complianceCondition = 'configTrue';
        return $this->getPolicyData($policyNames,$complianceCondition,$configurationNames);
    }

    // mfa needs to be enabled for user with owner permission
    // as here among the user Administrator with billing is considered SuperAdmin it is taken as owner
    // Standard mapping is "A.6.2.2" standard ISO 27001-2-2013
    //  how to implement:'https://support.jumpcloud.com/support/s/article/how-to-enable-multi-factor-authentication-for-the-jumpcloud-admin1-2019-08-21-10-36-47'
    public function getConditionalAccessStatus(): ?string
    {
        try {
            $administrators = $this->client->get('users?sort=email&filter=enableMultiFactor:eq:true&limit=1');
            if ($administrators->ok()) {
                $admin = json_decode($administrators, true);
                $apiResponse = collect($admin['results'])->whereIn('roleName','Administrator With Billing')->all();
                $required_values = ["enableMultiFactor" => true];
                $additional_values = ['_id', 'roleName', 'created'];
                $filter_operator = '=';

                return json_encode($this->formatResponse(
                    $apiResponse,
                    $required_values,
                    $additional_values,
                    $filter_operator
                ));
            }
            return null;
        } catch (\Exception $th) {
           writeLog('error', 'JumpCloud getConditionalAccessStatus implementation failed:'. $th->getMessage());
           return null;
        }
    }

    // check if there is password complexity
    // jumpCloud has password complexity in setting and is globally enabled
    // Standard mapping is "A.9.3.1" standard ISO 27001-2-2013
    //  how to implement:'https://support.jumpcloud.com/s/article/Password-Settings-in-the-JumpCloud-Admin-Portal'
    public function getPasswordComplexityStatus(): ?string
    {
        try {
            $allSettings = $this->client->get('/settings');

            if ($allSettings->ok()) {
                $data_to_return = $this->getPasswordSettings($allSettings);

                return $data_to_return ? json_encode($data_to_return) : null;
            }
            return null;
        } catch (\Exception$th) {
            writeLog('error', 'JumpCloud getPasswordComplexityStatus implementation failed:'. $th->getMessage());
            return null;
        }
    }

    // ntp (network time protocol) server
    // check if this policy is enabled and if the server is set
    // jumpCloud has 1 policies for this in mac
    // Standard mapping is "A.12.4.4" standard ISO 27001-2-201
    // implementation rate > 50 for control to be implemented

    public function getNtpStatus(): ?string
    {
        $policyNames =[
            '628b96bcedcc2f00010b4719'
        ];
        $configurationNames = [
            'setTimezone'
        ];
        $complianceCondition = 'configTrue';
        return $this->getPolicyData($policyNames,$complianceCondition,$configurationNames);
    }

    // custom Script policy
    // script can be found in fileRepository/Files/Sprint3-automation/technicalAutomation/PowerShellScript/individual/ActiveAntiVirus.ps1
    // this can be Added in jumpCloud from Command in SideBar
    // working: the script will check if 'CompliantActiveAntiVirus' exists in command response and takes data from last 60 mins
    // Standard mapping is "A.12.2.1" standard ISO 27001-2-2013
    //  how to implement:script
    // implementation rate > 50 for control to be implemented

    public function getAntivirusStatus(): ?string
    {
        return $this->getScriptPolicyData('CompliantActiveAntiVirus');
    }

    // custom Script policy
    // script can be found in fileRepository/Files/Sprint3-automation/technicalAutomation/PowerShellScript/individual/isNotAdmin.ps1
    // this can be Added in jumpCloud from Command in SideBar
    // working: the script will check if 'isCompliantToNotAdmin' exists in command response and takes data from last 60 mins
    // Standard mapping is "A.12.6.2" standard ISO 27001-2-2013
    // how to implement:script
    // implementation rate > 50 for control to be implemented
    public function getLocalAdminRestrictStatus(): ?string
    {
        return $this->getScriptPolicyData('isCompliantToNotAdmin');
    }

    // NTP status how to implement not found
    // 2 are custom script
    public static function getHowToImplementAction($action): ?string
    {
        $howToImplementActionsArr = [
            'adminPolicySetup' => 'https://support.jumpcloud.com/support/s/article/Basic-Policy-Procedures',
            'getMobileDeviceStatus' => 'https://support.jumpcloud.com/support/s/article/Basic-Policy-Procedures',
            'getBlockedUsbStatus' => 'https://support.jumpcloud.com/support/s/article/Manage-Removable-Storage-Using-a-Policy',
            'getInactivityStatus' => 'https://support.jumpcloud.com/support/s/article/Lock-Inactive-Screens-Using-a-Policy',
            'getHddEncryptionStatus' => 'https://support.jumpcloud.com/support/s/article/bitlocker-policy',
            'getTechVulnerabilitiesScanStatus' => 'https://support.jumpcloud.com/support/s/article/deploying-windows-updates-to-your-systems-with-windows-update-policy-2019-08-21-10-36-47',
            'getConditionalAccessStatus' => 'https://support.jumpcloud.com/support/s/article/how-to-enable-multi-factor-authentication-for-the-jumpcloud-admin1-2019-08-21-10-36-47',
            'getPasswordComplexityStatus' => 'https://support.jumpcloud.com/s/article/Password-Settings-in-the-JumpCloud-Admin-Portal',
            'getNtpStatus'=> '',
            'getAntivirusStatus' => '',
            'getLocalAdminRestrictStatus' => '',
        ];

        return array_key_exists($action, $howToImplementActionsArr) ? $howToImplementActionsArr[$action] : null;
    }

}
