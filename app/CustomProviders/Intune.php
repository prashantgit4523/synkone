<?php

namespace App\CustomProviders;

use App\CustomProviders\Interfaces\IDeviceManagement;
use App\CustomProviders\Interfaces\IHaveHowToImplement;
use App\Traits\Integration\IntegrationApiTrait;
use Illuminate\Support\Facades\Http;

class Intune extends CustomProvider implements IDeviceManagement, IHaveHowToImplement
{

    use IntegrationApiTrait;

    public function __construct()
    {
        parent::__construct('intune', 'https://login.microsoftonline.com/common/oauth2/v2.0/token');
    }

    public function getBlockedUsbStatus(): ?string
    {
        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get('https://graph.microsoft.com/beta/devicemanagement/deviceConfigurations', [
                    '$expand' => 'Assignments'
                ]);

            if ($response->ok()) {
                $body = json_decode($response->body(), true);

                $apiResponse = $body['value'] ?? $body;

                $required_values = ["usbBlocked" => true];
                $additional_values = ['id','displayName','description','assignments'];
                $filter_operator = '=';

                return json_encode($this->formatResponse(
                    $apiResponse,
                    $required_values,
                    $additional_values,
                    $filter_operator
                ));
            }
        } catch (\Exception $e) {
            writeLog('error', 'Intune getBlockedUsbStatus implementation failed: '. $e->getMessage());
        }

        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get('https://graph.microsoft.com/beta/deviceManagement/deviceCompliancePolicies', [
                    '$expand' => 'deviceStatusOverview'
                ]);

            if ($response->ok()) {
                $body = json_decode($response->body(), true);
                $result = $this->handleCustomPolicy($body['value'], '{"SettingName": "UsbBlockingCompliant"}');

                if ($result !== null) {
                    return json_encode($result);
                }
            }
        } catch (\Exception $e) {
            writeLog('error', 'Intune getBlockedUsbStatus implementation failed: '. $e->getMessage());
        }

        return null;
    }

    public function getPasswordComplexityStatus(): ?string
    {
        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get('https://graph.microsoft.com/beta/devicemanagement/deviceConfigurations', [
                    '$expand' => 'Assignments'
                ]);

            if ($response->ok()) {
                $body = json_decode($response->body(), true);

                $apiResponse = $body['value'] ?? $body;

                $required_values = ["passwordMinimumLength" => 8, "passwordMinimumCharacterSetCount" => 3];
                $additional_values = ['id','displayName','description','assignments'];
                $filter_operator = '>=';

                return json_encode($this->formatResponse(
                    $apiResponse,
                    $required_values,
                    $additional_values,
                    $filter_operator
                ));
            }
        } catch (\Exception $e) {
            writeLog('error', 'Intune getBlockedUsbStatus implementation failed: '. $e->getMessage());
        }

        return null;
    }

    public function getConditionalAccessStatus(): ?string
    {
        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get('https://graph.microsoft.com/v1.0/identity/conditionalAccess/policies');

            if ($response->ok()) {
                $body = json_decode($response->body(), true);

                $apiResponse = $body['value'] ?? $body;

                $required_values = ["state" => "enabled"];
                $additional_values = ['id','displayName'];
                $filter_operator = '=';

                return json_encode($this->formatResponse(
                    $apiResponse,
                    $required_values,
                    $additional_values,
                    $filter_operator,
                    '6.2.2'
                ));
            }
        } catch (\Exception $e) {
            writeLog('error', 'Intune getConditionalAccessStatus implementation failed: '. $e->getMessage());
        }

        return null;
    }

    public function getMobileDeviceStatus(): ?string
    {
        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get('https://graph.microsoft.com/beta/deviceManagement/deviceCompliancePolicies', [
                    '$expand' => 'assignments'
                ]);

            if ($response->ok()) {
                $body = json_decode($response->body(), true);

                $apiResponse = $body['value'] ?? $body;

                $required_values = ["version" => 1];
                $additional_values = ['id','displayName','description','assignments'];
                $filter_operator = '>=';

                return json_encode($this->formatResponse(
                    $apiResponse,
                    $required_values,
                    $additional_values,
                    $filter_operator
                ));
            }
        } catch (\Exception $e) {
            writeLog('error', 'Intune getMobileDeviceStatus implementation failed: '. $e->getMessage());
        }

        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get('https://graph.microsoft.com/v1.0/deviceAppManagement/iosManagedAppProtections');

            if ($response->ok()) {
                $body = json_decode($response->body(), true);

                $apiResponse = $body['value'] ?? $body;

                $required_values = ["isAssigned" => true];
                $additional_values = ['id','displayName','description'];
                $filter_operator = '=';

                return json_encode($this->formatResponse(
                    $apiResponse,
                    $required_values,
                    $additional_values,
                    $filter_operator
                ));
            }
        } catch (\Exception $e) {
            writeLog('error', 'Intune getMobileDeviceStatus implementation failed: '. $e->getMessage());
        }

        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get('https://graph.microsoft.com/v1.0/deviceAppManagement/androidManagedAppProtections');

            if ($response->ok()) {
                $body = json_decode($response->body(), true);

                $apiResponse = $body['value'] ?? $body;

                $required_values = ["isAssigned" => true];
                $additional_values = ['id','displayName','description'];
                $filter_operator = '=';

                return json_encode($this->formatResponse(
                    $apiResponse,
                    $required_values,
                    $additional_values,
                    $filter_operator
                ));
            }
        } catch (\Exception $e) {
            writeLog('error', 'Intune getMobileDeviceStatus implementation failed: '. $e->getMessage());
        }

        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get('https://graph.microsoft.com/v1.0/identity/conditionalAccess/policies');

            if ($response->ok()) {
                $body = json_decode($response->body(), true);

                $apiResponse = $body['value'] ?? $body;

                $required_values = [
                    "state" => "enabled",
                    "includeApplications" => [
                       "All",
                       "android",
                       "iOS"
                    ]
                ];

                $additional_values = ['id','displayName'];
                $filter_operator = '=';

                return json_encode($this->formatResponse(
                    $apiResponse,
                    $required_values,
                    $additional_values,
                    $filter_operator
                ));
            }
        } catch (\Exception $e) {
            writeLog('error', 'Intune getMobileDeviceStatus implementation failed: '. $e->getMessage());
        }

        return null;
    }

    public function getHddEncryptionStatus(): ?string
    {
        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get('https://graph.microsoft.com/beta/deviceManagement/deviceCompliancePolicies', [
                    '$expand' => 'deviceStatusOverview'
                ]);

            if ($response->ok()) {
                $body = json_decode($response->body(), true);
                $result = $this->handleCustomPolicy($body['value'], '{"SettingName": "BitLockerEncryptionCompliant"}');

                if ($result !== null) {
                    return json_encode($result);
                }
            }
        } catch (\Exception $e) {
            writeLog('error', 'Intune getHddEncryptionStatus implementation failed: '. $e->getMessage());
        }

        return null;
    }

    public function getInactivityStatus(): ?string
    {
        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get('https://graph.microsoft.com/beta/deviceManagement/deviceCompliancePolicies', [
                    '$expand' => 'deviceStatusOverview'
                ]);

            if ($response->ok()) {
                $body = json_decode($response->body(), true);
                $result = $this->handleCustomPolicy($body['value'], '{"SettingName": "InactivityPolicyCompliant"}');

                if ($result !== null) {
                    return json_encode($result);
                }
            }
        } catch (\Exception $e) {
            writeLog('error', 'Intune getInactivityStatus implementation failed: '. $e->getMessage());
        }

        return null;
    }

    public function getAntivirusStatus(): ?string
    {
        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get('https://graph.microsoft.com/beta/deviceManagement/deviceCompliancePolicies', [
                    '$expand' => 'deviceStatusOverview'
                ]);

            if ($response->ok()) {
                $body = json_decode($response->body(), true);
                $result = $this->handleCustomPolicy($body['value'], '{"SettingName": "CompliantActiveAntiVirus"}');

                if ($result !== null) {
                    return json_encode($result);
                }
            }
        } catch (\Exception $e) {
            writeLog('error', 'Intune getAntivirusStatus implementation failed: '. $e->getMessage());
        }

        return null;
    }

    public function getNtpStatus(): ?string
    {
        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get('https://graph.microsoft.com/beta/deviceManagement/deviceCompliancePolicies', [
                    '$expand' => 'deviceStatusOverview'
                ]);

            if ($response->ok()) {
                $body = json_decode($response->body(), true);
                $result = $this->handleCustomPolicy($body['value'], '{"SettingName": "PolicyCompliantStatus"}');

                if ($result !== null) {
                    return json_encode($result);
                }
            }
        } catch (\Exception $e) {
            writeLog('error', 'Intune getNtpStatus implementation failed: '. $e->getMessage());
        }

        return null;
    }

    public function getLocalAdminRestrictStatus(): ?string
    {
        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get('https://graph.microsoft.com/beta/deviceManagement/deviceCompliancePolicies', [
                    '$expand' => 'deviceStatusOverview'
                ]);

            if ($response->ok()) {
                $body = json_decode($response->body(), true);
                $result = $this->handleCustomPolicy($body['value'], '{"SettingName": "IsCompliantToNotAdmin"}');

                if ($result !== null) {
                    return json_encode($result);
                }
            }
        } catch (\Exception $e) {
            writeLog('error', 'Intune getLocalAdminRestrictStatus implementation failed: '. $e->getMessage());
        }

        return null;
    }

    //checks for autoupdates
    public function getTechVulnerabilitiesScanStatus(): ?string
    {
        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get('https://graph.microsoft.com/beta/deviceManagement/deviceCompliancePolicies', [
                    '$expand' => 'deviceStatusOverview'
                ]);

            if ($response->ok()) {
                $body = json_decode($response->body(), true);
                $result = $this->handleCustomPolicy($body['value'], '{"SettingName": "CompliantAutoUpdates"}');

                if ($result !== null) {
                    return json_encode($result);
                }
            }
        } catch (\Exception $e) {
            writeLog('error', 'Intune getTechVulnerabilitiesScanStatus implementation failed: '. $e->getMessage());
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
            "getTechVulnerabilitiesScanStatus" => "https://4sysops.com/archives/managing-windows-updates-with-microsoft-intune/"
        ];

        return array_key_exists($action, $howToImplementActionsArr) ? $howToImplementActionsArr[$action] : null;
    }
}
