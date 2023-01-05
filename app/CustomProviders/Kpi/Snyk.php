<?php

namespace App\CustomProviders\Kpi;

use App\CustomProviders\CustomProvider;
use App\CustomProviders\Interfaces\ISecurityAnalysis;
use App\CustomProviders\Interfaces\ICustomAuth;
use App\CustomProviders\Interfaces\IHaveHowToImplement;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use App\Traits\Integration\IntegrationApiTrait;
use Carbon\Carbon;

class Snyk extends CustomProvider implements ISecurityAnalysis, ICustomAuth, IHaveHowToImplement
{

    use IntegrationApiTrait;
    private PendingRequest $client;
    private string $apiToken;


    public function __construct()
    {
        parent::__construct('snyk');
        $this->client = Http::baseUrl('https://api.snyk.io/api/v1')->timeout(10);
    }

    public function attempt(array $fields): bool
    {
        $this->apiToken = $fields['api_token'];

        $response = $this->client->withHeaders([
            "Authorization" => $this->apiToken
        ])->get('/user');

        if ($response->failed()) {
            return false;
        }

        $this->connect($this->provider, $fields);

        return true;
    }

    public function getTechnicalVulnerabilitiesScanStatus(): ?string
    {
        try {
            $response = $this->httpToken()->get('https://api.snyk.io/api/v1/orgs');

            if ($response->ok()) {
                $body = json_decode($response->body(), true);
                $organizations = $body['orgs'] ?? $body;
                foreach ($organizations as $organization) {
                    $allProjects = $this->httpToken()->post('https://api.snyk.io/api/v1/org/${organization["id"]}/projects');
                    if ($allProjects->ok()) {
                        $retrieveProjects = json_decode($allProjects->body(), true);
                        foreach ($retrieveProjects['projects'] as $retrieveProject) {
                            $projectVul = $this->httpToken()->post('https://api.snyk.io/api/v1/org/'.$organization["id"].'/project/'.$retrieveProject["id"].'/aggregated-issues');
                            dd(json_decode($projectVul->body(), true));
                            if (array_key_exists('lastTestedDate', $retrieveProject) && Carbon::parse($retrieveProject['lastTestedDate'])->diffInMonths() < 3) {
                                $additionalValues = ['name',  'email', 'origin', 'branch', 'lastTestedDate'];
                                return json_encode($this->formatResponse(
                                    [$retrieveProject],
                                    [],
                                    $additionalValues,
                                    '='
                                ));
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            writeLog('error', 'Snyk getTechnicalVulnerabilitiesScanStatus implementation failed: '. $e->getMessage());
        }
        return null;
    }

    public function httpToken()
    {
        return Http::withHeaders(['Authorization' => decrypt($this->provider->accessToken)]);
    }

    public static function getHowToImplementAction($action): ?string
    {
        $howToImplementActionsArr = [
            "getTechnicalVulnerabilitiesScanStatus" => "",
        ];

        return array_key_exists($action, $howToImplementActionsArr) ? $howToImplementActionsArr[$action] : null;
    }
}
