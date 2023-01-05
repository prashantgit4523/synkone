<?php

namespace App\CustomProviders;

use App\CustomProviders\Interfaces\ISecurityAnalysis;
use App\CustomProviders\Interfaces\ICustomAuth;
use App\CustomProviders\Interfaces\IHaveHowToImplement;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use App\Traits\Integration\IntegrationApiTrait;
use Carbon\Carbon;

class SonarQube extends CustomProvider implements ISecurityAnalysis, ICustomAuth, IHaveHowToImplement
{
    use IntegrationApiTrait;
    private PendingRequest $client;
    private string $apiToken;
    private string $url;

    public function __construct()
    {
        parent::__construct('sonarqube');

        $this->url = $this->fields['url'];
        $this->apiToken = $this->fields['api_key'];

        $this->client = Http::withBasicAuth($this->apiToken, '')
            ->timeout(10)
            ->baseUrl($this->url);
    }

    public function attempt(array $fields): bool
    {
        if (
            $this->client->timeout(5)
            ->withBasicAuth($fields['api_key'], '')
            ->baseUrl($this->getBaseUrl($fields['url']))
            ->get('api/projects/search')
            ->failed()
        ) {
            return false;
        }

        $this->connect($this->provider, $fields);
        return true;
    }
    public function getTechnicalVulnerabilitiesScanStatus(): ?string
    {
        try {
            $response = $this->client->withBasicAuth($this->apiToken, '')->get('api/projects/search');
            if ($response->ok()) {
                $body = json_decode($response->body(), true);
                $apiResponse = $body['components'] ?? $body;
                foreach ($apiResponse as $project) {
                    if (array_key_exists('lastAnalysisDate', $project) && Carbon::parse($project['lastAnalysisDate'])->diffInMonths() < 3) {
                        $additionalValues = ['qualifier', 'key', 'name', 'lastAnalysisDate'];
                        return json_encode($this->formatResponse(
                            [$project],
                            [],
                            $additionalValues,
                            '='
                        ));
                    }
                }
            }
        } catch (\Exception $e) {
            writeLog('error', 'SonarQube getTechnicalVulnerabilitiesScanStatus implementation failed: '. $e->getMessage());
        }

        return null;
    }

    public static function getHowToImplementAction($action): ?string
    {
        $howToImplementActionsArr = [
            "getTechnicalVulnerabilitiesScanStatus" => "https://docs.sonarqube.org/latest/setup/get-started-2-minutes/",
        ];

        return array_key_exists($action, $howToImplementActionsArr) ? $howToImplementActionsArr[$action] : null;
    }
}
