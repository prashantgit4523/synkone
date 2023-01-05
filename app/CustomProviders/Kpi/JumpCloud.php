<?php

namespace App\CustomProviders\Kpi;

use App\CustomProviders\CustomProvider;
use App\CustomProviders\Interfaces\IDeviceManagement;
use App\CustomProviders\Interfaces\IHaveHowToImplement;
use App\Traits\Integration\IntegrationApiTrait;
use App\Traits\Integration\JumpCloudApiTrait;
use App\Traits\Kpi\jumpCloudKpiTrait;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;

class JumpCloud extends CustomProvider implements IHaveHowToImplement,IDeviceManagement
{
    private PendingRequest $client;
    use IntegrationApiTrait;
    use JumpCloudApiTrait, jumpCloudKpiTrait;

     //note:
    //In jumpCLoud there are policies differntiated by OS
    //And as such, policies from one OS can not be applied to another
    //Thus, when taking Total for KPI or To check implemetation level we need to take devices 
    // from each OS that can have the policy applied to it whether or not the policy is created/Implemented

    //for more details on how these functions work please visit jumpClpud.php(jumpClouds integration)
    //as the differne between them is KPI option is set true in function getPolicyData();
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
            ->baseUrl('https://console.jumpcloud.com/api')
            ->get('/api/v2/policies')
            ->failed()) {
            return false;
        }

        $this->connect($this->provider, $fields);
        return true;
    }

    //KPI: Number of devices with assigned noncompliant policy.
    //logic: no of devices that are assigned policies but are non complaint
    //passed: number of device with any policy success as false 
    //total: total no of unique device
    public function getMobileDeviceStatus(): ?string
    {
        $getAllDevices = $this->policyResult;
        $passed =   collect($getAllDevices['nonCompliant'])->unique('systemID')->count();
        $total  =   collect($getAllDevices['totalApplied'])->unique('systemID')->count();
        return json_encode([
            'passed'=>$passed,
            'total'=>$total,
        ]);
    }

    public function getBlockedUsbStatus(): ?string
    {
        $policyNames = [
            '5ccb6247232e1105bf44e854',
            '5e56e0d41f24753d06ae449a',
        ];
        $configuartioNames = [
            'RemovableStorageClasses_DenyAll_Access_2',
            'externalHardDrives',
        ];
        $complainceCondition = 'configTrue';
        return $this->getPolicyData($policyNames, $complainceCondition, $configuartioNames, true);
    }

    public function getInactivityStatus(): ?string
    {
        $policyNames = [
            '59b6ee62232e1123015d70b2',
            '599b2bad232e117f7dbaac08',
            '619bfad51f2475277753697a',
        ];
        $configuartioNames = [
            'timeout',
        ];
        $complainceCondition = 'Inactivity';
        return $this->getPolicyData($policyNames, $complainceCondition, $configuartioNames, true);
    }

    public function getHddEncryptionStatus(): ?string
    {
        $policyNames = [
            '5beca66c232e1104b9d7d4cb',
        ];
        $complainceCondition = 'enabled';
        return $this->getPolicyData($policyNames, $complainceCondition, false, true);
    }

    public function getTechVulnerabilitiesScanStatus(): ?string
    {
        $policyNames = [
            '5c1be3d4232e1109e84f625d',
        ];
        $configuartioNames = [
            'AUTO_INSTALL_UPDATES',
            'AUTO_INSTALL_MINOR_UPDATES',
            'AUTO_INSTALL_DURING_MAINT',
        ];
        $complainceCondition = 'configTrue';
        return $this->getPolicyData($policyNames, $complainceCondition, $configuartioNames, true);
    }

    public function getConditionalAccessStatus(): ?string
    {
        try {
            return $this->getConditionalAccessStatusKpiData();
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with running getConditionalAccessStatus on JumpCloud');
        }
        return null;
    }

    public function getPasswordComplexityStatus(): ?string
    {
        return $this->getPasswordComplexityKpiData();
    }

    public function getNtpStatus(): ?string
    {
        $policyNames = [
            '628b96bcedcc2f00010b4719',
        ];
        $configuartioNames = [
            'setTimezone',
        ];
        $complainceCondition = 'configTrue';
        return $this->getPolicyData($policyNames, $complainceCondition, $configuartioNames, true);
    }

    public function getAntivirusStatus(): ?string
    {
        return $this->getScriptPolicyData('CompliantActiveAntiVirus', true);
    }

    public function getLocalAdminRestrictStatus(): ?string
    {
        return $this->getScriptPolicyData('isCompliantToNotAdmin', true);
    }

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
            'getNtpStatus' => '',
            'getAntivirusStatus' => '',
            'getLocalAdminRestrictStatus' => '',
        ];

        return array_key_exists($action, $howToImplementActionsArr) ? $howToImplementActionsArr[$action] : null;
    }
}
