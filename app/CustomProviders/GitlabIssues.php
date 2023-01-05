<?php

namespace App\CustomProviders;

use App\CustomProviders\Interfaces\IHaveHowToImplement;
use App\CustomProviders\Interfaces\ITicketing;
use Illuminate\Support\Facades\Http;
use App\Traits\Integration\IntegrationApiTrait;

class GitlabIssues extends CustomProvider implements ITicketing, IHaveHowToImplement
{
    use IntegrationApiTrait;

    public function __construct()
    {
        parent::__construct('gitlab-issues', 'https://gitlab.com/oauth/token');
    }

    public function getMfaStatus(): ?string
    {
        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get('https://gitlab.com/api/v4/user');

            if ($response->ok()) {
                $body = json_decode($response->body(), true);

                $apiResponse = $body['value'] ?? $body;

                $required_values = ["two_factor_enabled" => true];
                $additional_values = ['id','name'];
                $filter_operator = '=';

                return json_encode($this->formatResponse(
                    $apiResponse,
                    $required_values,
                    $additional_values,
                    $filter_operator
                ));
            }

        } catch (\Exception $e) {
            writeLog('error', 'GitlabIssues getMfastatus implementation failed: '.$e->getMessage());
        }

        return null;
    }

    public static function getHowToImplementAction($action): ?string
    {
        $howToImplementActionsArr = [
            "getMfaStatus" => "https://docs.gitlab.com/ee/user/profile/account/two_factor_authentication.html#enable-two-factor-authentication",
        ];

        return array_key_exists($action, $howToImplementActionsArr) ? $howToImplementActionsArr[$action] : null;
    }
}
