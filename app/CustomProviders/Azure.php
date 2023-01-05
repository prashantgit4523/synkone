<?php

namespace App\CustomProviders;

use App\CustomProviders\Interfaces\IHaveHowToImplement;
use Illuminate\Support\Facades\Http;
use App\Traits\Integration\IntegrationApiTrait;
use App\CustomProviders\Interfaces\IInfrastructure;

class Azure extends CustomProvider implements IInfrastructure, IHaveHowToImplement
{
    use IntegrationApiTrait;

    public function __construct()
    {
        parent::__construct('azure', 'https://login.microsoftonline.com/common/oauth2/v2.0/token');
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
        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get('https://graph.microsoft.com/beta/me/informationProtection/policy/labels?$filter=isActive eq true');

            if ($response->ok()) {
                $body = json_decode($response->body(), true);

                $apiResponse = $body['value'] ?? $body;

                $required_values = ["isActive" => true];
                $additional_values = ['id', 'name', 'description', 'tooltip'];
                $filter_operator = '=';

                foreach ($apiResponse as $key => $val) {
                    unset($apiResponse[$key]['parent']);
                }

                return json_encode($this->formatResponse(
                    $apiResponse,
                    $required_values,
                    $additional_values,
                    $filter_operator
                ));
            }

        } catch (\Exception $e) {
            writeLog('error', 'Azure getClassificationStatus implementation failed: '.$e->getMessage());
        }

        return null;
    }

    public function getInactiveUsersStatus(): ?string
    {
        $datetime = date('Y-m-d', strtotime("-6 months"));

        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get('https://graph.microsoft.com/beta/users', [
                    '$select' => 'id,displayName',
                    'filter' => 'signInActivity/lastSignInDateTime le ' . $datetime
                ]);

            if ($response->ok()) {
                $body = json_decode($response->body(), true);

                $apiResponse = $body['value'] ?? $body;

                if (!empty($apiResponse)) {
                    return null;
                } else {
                    return json_encode(["message" => "All users are logged in within 6 months of period."]);
                }
            }
        } catch (\Exception $e) {
        }

        return null;
    }

    public function getSecureDataWipingStatus(): ?string
    {
        return json_encode(["message" => "Azure is deleting all data securely."]);
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
        $howToImplementActionsArr = [
            "getClassificationStatus" => "https://docs.microsoft.com/en-us/microsoft-365/compliance/create-sensitivity-labels?view=o365-worldwide",
        ];

        return array_key_exists($action, $howToImplementActionsArr) ? $howToImplementActionsArr[$action] : null;
    }
}
