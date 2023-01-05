<?php

namespace App\CustomProviders\Kpi;

use Illuminate\Support\Facades\Http;
use App\CustomProviders\CustomProvider;
use App\Traits\Integration\IntegrationApiTrait;
use App\CustomProviders\Interfaces\IInfrastructure;
use App\CustomProviders\Interfaces\IHaveHowToImplement;

class AzureServiceManagement extends CustomProvider implements IInfrastructure, IHaveHowToImplement
{
    use IntegrationApiTrait;

    public function __construct()
    {
        parent::__construct('azure-service-management', 'https://login.microsoftonline.com/common/oauth2/v2.0/token');
    }

    public function getWafStatus(): ?string
    {
        return null;
    }

    public function getMfaStatus(): ?string
    {
        return null;
    }

    public function getKeyvaultStatus(): ?string
    {
        return null;
    }

    public function getLoggingStatus(): ?string
    {
        return null;
    }

    public function getCpuMonitorStatus(): ?string
    {
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

                $data['total'] = 0;
                $data['passed'] = 0;

                foreach ($response as $res) {
                    $vaultName = $res['name'];
                    $resourceName = explode('/', $res['id'])[4];

                    $apiResponse = Http::withToken($this->provider->accessToken)
                        ->get("https://management.azure.com/subscriptions/{$this->provider->subscriptionId}/resourceGroups/{$resourceName}/providers/Microsoft.RecoveryServices/vaults/{$vaultName}/backupJobs", [
                            "api-version" => "2021-02-10"
                        ]);

                    $apiResponse = json_decode($apiResponse->body(), true);
                    if (count($apiResponse['value']) > 0) {
                        foreach ($apiResponse['value'] as $Bjd) {
                            $data['total']++;
                            if ($Bjd['properties']['status'] == 'Completed')
                                $data['passed']++;
                        }
                    }


                }

                if($data['total'] > 0)
                    return json_encode($data);
            }
            
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with getBackupsStatus on Azure Service Management');
        }       

        return null;
    }

    public function getNetworkSegregationStatus(): ?string
    {
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
