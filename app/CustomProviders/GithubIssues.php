<?php

namespace App\CustomProviders;

use App\CustomProviders\Interfaces\IHaveHowToImplement;
use Illuminate\Support\Facades\Http;
use App\Traits\Integration\IntegrationApiTrait;

class GithubIssues extends CustomProvider implements Interfaces\ITicketing, IHaveHowToImplement
{
    use IntegrationApiTrait;

    public function __construct()
    {
        parent::__construct('github-issues', 'https://github.com/login/oauth/access_token');
    }

    public function getMfaStatus(): ?string
    {
        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get('https://api.github.com/user');

                if ($response->ok()) {
                    $body = json_decode($response->body(), true);

                    $apiResponse = $body['value'] ?? $body;

                    $required_values = ["two_factor_authentication" => true];
                    $additional_values = ['id','login'];
                    $filter_operator = '=';

                    return json_encode($this->formatResponse(
                        $apiResponse,
                        $required_values,
                        $additional_values,
                        $filter_operator
                    ));
                }

        } catch (\Exception $e) {
            writeLog('error', 'GithubIssues getMfaStatus implementation failed: '.$e->getMessage());
        }

        return null;

    }

    public static function getHowToImplementAction($action): ?string
    {
        $howToImplementActionsArr = [
            "getMfaStatus" => "https://docs.github.com/en/authentication/securing-your-account-with-two-factor-authentication-2fa/configuring-two-factor-authentication",
        ];

        return array_key_exists($action, $howToImplementActionsArr) ? $howToImplementActionsArr[$action] : null;
    }
}
