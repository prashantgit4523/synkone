<?php

namespace App\CustomProviders;

use App\CustomProviders\Interfaces\IHaveHowToImplement;
use Illuminate\Support\Facades\Http;
use App\Traits\Integration\IntegrationApiTrait;
use App\CustomProviders\Interfaces\IInfrastructure;

class AzureServiceManagement extends CustomProvider implements IInfrastructure, IHaveHowToImplement
{
    use IntegrationApiTrait;

    public function __construct()
    {
        parent::__construct('azure-service-management', 'https://login.microsoftonline.com/common/oauth2/v2.0/token');
    }

    public function getWafStatus(): ?string
    {
        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get("https://management.azure.com/subscriptions/{$this->provider->subscriptionId}/providers/Microsoft.Network/ApplicationGatewayWebApplicationFirewallPolicies", [
                    'api-version' => '2021-05-01'
                ]);

            if ($response->ok()) {
                $body = json_decode($response->body(), true);

                $apiResponse = $body['value'] ?? $body;

                $required_values = ["state" => "Enabled"];
                $additional_values = ['id', 'name'];
                $filter_operator = '=';

                return json_encode($this->formatResponse(
                    $apiResponse,
                    $required_values,
                    $additional_values,
                    $filter_operator
                ));
            }
        } catch (\Exception $e) {
            writeLog('error', 'AzureServiceManagement getWafStatus implementation failed: '.$e->getMessage());
        }

        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get("https://management.azure.com/subscriptions/{$this->provider->subscriptionId}/providers/Microsoft.Network/frontDoorWebApplicationFirewallPolicies", [
                    'api-version' => '2020-11-01'
                ]);

            if ($response->ok()) {
                $body = json_decode($response->body(), true);

                $apiResponse = $body['value'] ?? $body;

                $required_values = ["enabledState" => "Enabled"];
                $additional_values = ['id', 'name'];
                $filter_operator = '=';

                return json_encode($this->formatResponse(
                    $apiResponse,
                    $required_values,
                    $additional_values,
                    $filter_operator
                ));
            }
        } catch (\Exception $e) {
            writeLog('error', 'AzureServiceManagement getWafStatus implementation failed: '.$e->getMessage());
        }

        return null;
    }

    public function getMfaStatus(): ?string
    {
        return null;
    }

    public function getKeyvaultStatus(): ?string
    {
        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get("https://management.azure.com/subscriptions/{$this->provider->subscriptionId}/resources", [
                    'api-version' => '2021-01-01',
                    '$filter' => "resourceType eq 'Microsoft.KeyVault/vaults'"
                ]);

            if ($response->ok()) {
                $body = json_decode($response->body(), true);

                $apiResponse = $body['value'] ?? $body;

                $required_values = ["type" => "Microsoft.KeyVault/vaults"];
                $additional_values = ['id', 'name'];
                $filter_operator = '=';

                return json_encode($this->formatResponse(
                    $apiResponse,
                    $required_values,
                    $additional_values,
                    $filter_operator
                ));
            }
        } catch (\Exception $e) {
            writeLog('error', 'AzureServiceManagement getKeyvaultStatus implementation failed: '.$e->getMessage());
        }

        return null;
    }

    public function getLoggingStatus(): ?string
    {
        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get("https://management.azure.com/subscriptions/{$this->provider->subscriptionId}/providers/Microsoft.Insights/activityLogAlerts", [
                    'api-version' => '2020-10-01'
                ]);

            if ($response->ok()) {
                $body = json_decode($response->body(), true);

                $apiResponse = $body['value'] ?? $body;

                $required_values = ["enabled" => true];
                $additional_values = ['id', 'name'];
                $filter_operator = '=';

                return json_encode($this->formatResponse(
                    $apiResponse,
                    $required_values,
                    $additional_values,
                    $filter_operator
                ));
            }
        } catch (\Exception $e) {
            writeLog('error', 'AzureServiceManagement getLoggingStatus implementation failed: '.$e->getMessage());
        }

        return null;
    }

    public function getCpuMonitorStatus(): ?string
    {
        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get("https://management.azure.com/subscriptions/{$this->provider->subscriptionId}/providers/Microsoft.Insights/activityLogAlerts", [
                    'api-version' => '2020-10-01'
                ]);

            if ($response->ok()) {
                $body = json_decode($response->body(), true);

                $apiResponse = $body['value'] ?? $body;

                $required_values = ["enabled" => true];
                $additional_values = ['id', 'name'];
                $filter_operator = '=';

                return json_encode($this->formatResponse(
                    $apiResponse,
                    $required_values,
                    $additional_values,
                    $filter_operator
                ));
            }
        } catch (\Exception $e) {
            writeLog('error', 'AzureServiceManagement getCpuMonitorStatus implementation failed: '.$e->getMessage());
        }

        return null;
    }

    public function getBackupsStatus(): ?string
    {
        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get("https://management.azure.com/subscriptions/{$this->provider->subscriptionId}/providers/Microsoft.RecoveryServices/vaults", [
                    'api-version' => '2016-06-01'
                ]);

            if ($response->ok()) {
                $response = json_decode($response->body(), true);

                $response = $response['value'] ?? $response;

                foreach ($response as $res) {
                    $vaultName = $res['name'];
                    $resourceName = explode('/', $res['id'])[4];

                    $apiResponse = Http::withToken($this->provider->accessToken)
                        ->get("https://management.azure.com/subscriptions/{$this->provider->subscriptionId}/resourceGroups/{$resourceName}/providers/Microsoft.RecoveryServices/vaults/{$vaultName}/backupJobs", [
                            "api-version" => "2021-02-10"
                        ]);

                    $apiResponse = json_decode($apiResponse->body(), true);

                    $apiResponse = $apiResponse['value'] ?? $apiResponse;

                    if (!$apiResponse) {
                        continue;
                    }

                    $required_values = ["type" => "Microsoft.RecoveryServices/vaults/backupJobs"];
                    $additional_values = ['id', 'name', 'type', 'startTime', 'endTime'];
                    $filter_operator = '=';

                    return json_encode($this->formatResponse(
                        $apiResponse,
                        $required_values,
                        $additional_values,
                        $filter_operator
                    ));
                }
            }
        } catch (\Exception $e) {
            writeLog('error', 'AzureServiceManagement getBackupsStatus implementation failed: '.$e->getMessage());
        }

        return null;
    }

    public function getNetworkSegregationStatus(): ?string
    {
        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get("https://management.azure.com/subscriptions/{$this->provider->subscriptionId}/providers/Microsoft.Network/networkSecurityGroups", [
                    'api-version' => '2021-05-01'
                ]);

            if ($response->ok()) {
                $body = json_decode($response->body(), true);

                $apiResponse = $body['value'] ?? $body;

                $required_values = ["access" => "Deny"];
                $additional_values = ['id', 'name', 'type'];
                $filter_operator = '=';

                return json_encode($this->formatResponse(
                    $apiResponse,
                    $required_values,
                    $additional_values,
                    $filter_operator
                ));
            }
        } catch (\Exception $e) {
            writeLog('error', 'AzureServiceManagement getNetworkSegregationStatus implementation failed: '.$e->getMessage());
        }

        return null;
    }

    public function getClassificationStatus(): ?string
    {
        return null;
    }

    public function getInactiveUsersStatus(): ?string
    {
        return null;
    }

    public function getSecureDataWipingStatus(): ?string
    {
        return null;
    }

    public function getAdminMfaStatus(): ?string
    {
        return null;
    }

    public function getHddEncryptionStatus(): ?string
    {
        return null;
    }

    public static function getHowToImplementAction($action): ?string
    {
        // Can also go with this, todo:: ask Amar to pick one (?)
        // "getWafStatus" => "https://docs.microsoft.com/en-us/azure/web-application-firewall/afds/waf-front-door-create-portal",

        $howToImplementActionsArr = [
            "getWafStatus" => "https://docs.microsoft.com/en-us/azure/web-application-firewall/ag/create-waf-policy-ag",
            "getKeyvaultStatus" => "https://docs.microsoft.com/en-us/azure/key-vault/general/quick-create-portal",
            "getLoggingStatus" => "https://docs.microsoft.com/en-us/azure/azure-monitor/alerts/alerts-metric",
            "getCpuMonitorStatus" => "https://docs.microsoft.com/en-us/azure/azure-monitor/alerts/alerts-metric",
            "getBackupsStatus" => "https://docs.microsoft.com/en-us/azure/backup/backup-azure-arm-vms-prepare",
            "getNetworkSegregationStatus" => "https://docs.microsoft.com/en-us/azure/virtual-machines/windows/nsg-quickstart-portal"
        ];

        return array_key_exists($action, $howToImplementActionsArr) ? $howToImplementActionsArr[$action] : null;
    }
}
