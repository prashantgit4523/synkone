<?php

namespace App\CustomProviders\Kpi;

use Illuminate\Support\Facades\Http;
use App\CustomProviders\CustomProvider;
use App\Traits\Integration\IntegrationApiTrait;
use App\CustomProviders\Interfaces\IInfrastructure;
use App\CustomProviders\Interfaces\IHaveHowToImplement;

class AzureActiveDirectory extends CustomProvider implements IInfrastructure, IHaveHowToImplement
{
    use IntegrationApiTrait;

    public function __construct()
    {
        parent::__construct('azure-active-directory', 'https://login.microsoftonline.com/common/oauth2/v2.0/token');
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

    // condition: admin mfa users with no MFA enabled
    // Standard: ISO 27001-2-2013
    // control : A.9.4.1
    public function getAdminMfaStatus(): ?string
    {
        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get('https://graph.microsoft.com/v1.0/Security/secureScores', [
                    '$top' => 1,
                    '$select' => 'controlScores'
                ]);
                
            if ($response->ok()) {
                $body = json_decode($response->body(), true);

                $apiResponse = $body['value'] ?? $body;
                $data['total'] = 0;
                $data['passed'] = 0;
                foreach ($apiResponse[0]['controlScores'] as $value) {
                    if ($value['controlName'] == "AdminMFAV2") {
                        $data['passed'] = $value['count'];
                        $data['total'] = $value['total'];
                    }
                }
                
                if ($data['total'] > 0) {
                    return json_encode($data);
                }
            }
        } catch (\Exception $e) {
            writeLog('error', $e->getMessage());
        }
        return null;
    }

    public function getHddEncryptionStatus(): ?string
    {
        return null;
    }

    public static function getHowToImplementAction($action): ?string
    {
        $howToImplementActionsArr = [
            "getAdminMfaStatus" => "https://docs.microsoft.com/ro-ro/azure/active-directory/conditional-access/howto-conditional-access-policy-admin-mfa"
        ];

        return array_key_exists($action, $howToImplementActionsArr) ? $howToImplementActionsArr[$action] : null;
    }
}
