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
            $totalVulnerability = 0;
            $criticalVulnerability = 0;
            $curPage = 1;
            do {
                $response = $this->client->withBasicAuth($this->apiToken, '')
                                ->get('api/issues/search?p='.$curPage.'&ps=500');

                $pageIndex = $response['paging']['pageIndex'];
                $pageSize = $response['paging']['pageSize'];
                $total = $response['paging']['total'];
                $pageVal = $total - ($pageIndex*$pageSize);
                if ($response->ok()) {
                    $body = json_decode($response->body(), true);
                    $apiResponse = $body['issues'] ?? $body;
                    foreach ($apiResponse as $res) {
                        if ($res['type'] == "VULNERABILITY") {
                            $totalVulnerability++;
                            if ($res['severity'] == "CRITICAL") {
                                $criticalVulnerability++;
                            }
                        }
                    }
                }
                $curPage++;
            }while($pageVal > 0);

            return json_encode([
                'passed' => $totalVulnerability - $criticalVulnerability,
                'total'=> $totalVulnerability
            ]);
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
