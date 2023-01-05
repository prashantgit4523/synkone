<?php

namespace App\CustomProviders\Kpi;

use App\CustomProviders\CustomProvider;
use App\CustomProviders\Interfaces\ISecurityAnalysis;
use App\CustomProviders\Interfaces\ICustomAuth;
use App\CustomProviders\Interfaces\IHaveHowToImplement;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use App\Traits\Integration\IntegrationApiTrait;

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
            $totalVulnerability = 0;
            $criticalVulnerability = 0;
            $response = Http::withToken(decrypt($this->provider->accessToken))
                            ->get('https://sonarcloud.io/api/organizations/search?member=true');

            if ($response->ok()) {
                $body = json_decode($response->body(), true);
                $apiResponse = $body['organizations'] ?? $body;
                foreach ($apiResponse as $org) {
                    $getProjectList = Http::withToken(decrypt($this->provider->accessToken))
                                    ->get("https://sonarcloud.io/api/components/search?organization=" . $org['name']);
                    $projectList = json_decode($getProjectList->body(), true);
                    foreach ($projectList['components'] as $project) {
                        $curPage = 1;
                        do {
                            $getTechnicalVulnerabilities = Http::withToken(decrypt($this->provider->accessToken))
                                                        ->get("https://sonarcloud.io/api/issues/search?projectKeys=" .
                                                                $project['project'] . "&p=".
                                                                $curPage ."&ps=500");
                            $pageIndex = $getTechnicalVulnerabilities['paging']['pageIndex'];
                            $pageSize = $getTechnicalVulnerabilities['paging']['pageSize'];
                            $total = $getTechnicalVulnerabilities['paging']['total'];
                            $pageVal = $total - ($pageIndex*$pageSize);
                            
                            if ($getTechnicalVulnerabilities->ok()){
                                $technicalVulnerabilities = json_decode($getTechnicalVulnerabilities->body(), true);
                                foreach ($technicalVulnerabilities['issues'] as $technicalVulnerability){
                                    if ($technicalVulnerability['type'] == "VULNERABILITY") {
                                        $totalVulnerability++;
                                        if ($technicalVulnerability['severity'] == "CRITICAL") {
                                            $criticalVulnerability++;
                                        }
                                    }
                                }
                            }
                            $curPage++;
                        }while($pageVal > 0);
                    }
                }
            }
            return json_encode([
                'passed' => $totalVulnerability - $criticalVulnerability,
                'total'=> $totalVulnerability
            ]);
        } catch (\Exception $e) {
            writeLog('error', 'SonarCloud getTechnicalVulnerabilitiesScanStatus: '.$e->getMessage());
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
