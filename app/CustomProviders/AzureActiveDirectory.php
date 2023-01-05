<?php

namespace App\CustomProviders;


use App\CustomProviders\Interfaces\IHaveHowToImplement;
use Illuminate\Support\Facades\Http;
use App\Traits\Integration\IntegrationApiTrait;
use App\CustomProviders\Interfaces\IInfrastructure;

class AzureActiveDirectory extends CustomProvider implements IInfrastructure, IHaveHowToImplement
{
    use IntegrationApiTrait;

    public function __construct()
    {
        parent::__construct('azure-active-directory', 'https://login.microsoftonline.com/common/oauth2/v2.0/token');
    }

    public function getEmailEncryptionStatus(): ?string
    {
        return json_encode(["office 365 email encryption" => true]);
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

                $required_values = ["controlName" => "AdminMFAV2","scoreInPercentage" => 100];
                $additional_values = ['description','implementationStatus'];
                $filter_operator = '=';

                return json_encode($this->formatResponse(
                    $apiResponse,
                    $required_values,
                    $additional_values,
                    $filter_operator
                ));
            }
        } catch (\Exception $e) {
            writeLog('error', 'AzureActiveDirectory getAdminMfaStatus implementation failed: '.$e->getMessage());
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
