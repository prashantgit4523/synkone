<?php

namespace App\CustomProviders;

use App\CustomProviders\Interfaces\ISecurityAnalysis;
use App\CustomProviders\Interfaces\ICustomAuth;
use App\CustomProviders\Interfaces\IHaveHowToImplement;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use App\Traits\Integration\IntegrationApiTrait;
use Carbon\Carbon;

class SonarCloud extends CustomProvider implements ISecurityAnalysis, ICustomAuth, IHaveHowToImplement
{
    use IntegrationApiTrait;
    private PendingRequest $client;
    private string $apiToken;

    public function __construct()
    {
        parent::__construct('sonarcloud');
        $this->client = Http::baseUrl('https://sonarcloud.io/api/');
    }

    public function attempt(array $fields): bool
    {
        $this->apiToken = $fields['api_token'];
        $response = $this->client->withToken($this->apiToken)->get('organizations/search?member=true');

        if ($response->failed()) {
            return false;
        }

        $this->connect($this->provider, $fields);

        return true;
    }

    public function getTechnicalVulnerabilitiesScanStatus(): ?string
    {
        try {
            $response = Http::withToken(decrypt($this->provider->accessToken))->get('https://sonarcloud.io/api/organizations/search?member=true');

            if ($response->ok()) {
                $body = json_decode($response->body(), true);
                $apiResponse = $body['organizations'] ?? $body;
                foreach ($apiResponse as $org) {
                    $getTechnicalVulnerabilities = Http::withToken(decrypt($this->provider->accessToken))->get("https://sonarcloud.io/api/projects/search?organization=" . $org['name']);
                    $technicalVulnerabilities = json_decode($getTechnicalVulnerabilities->body(), true);
                    foreach ($technicalVulnerabilities['components'] as $technicalVulnerabilitie) {
                        if (array_key_exists('lastAnalysisDate', $technicalVulnerabilitie) && Carbon::parse($technicalVulnerabilitie['lastAnalysisDate'])->diffInMonths() < 3) {
                            $additionalValues = ['organization', 'key', 'name', 'lastAnalysisDate'];
                            return json_encode($this->formatResponse(
                                [$technicalVulnerabilitie],
                                [],
                                $additionalValues,
                                '='
                            ));
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            writeLog('error', 'SonarCloud getTechnicalVulnerabilitiesScanStatus implementation failed: '. $e->getMessage());
        }

        return null;
    }

    public static function getHowToImplementAction($action): ?string
    {
        $howToImplementActionsArr = [
            "getTechnicalVulnerabilitiesScanStatus" => "https://sonarcloud.io/web_api/api/projects/search?deprecated=false",
        ];

        return array_key_exists($action, $howToImplementActionsArr) ? $howToImplementActionsArr[$action] : null;
    }
}
