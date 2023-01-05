<?php

namespace App\CustomProviders\Kpi;

use App\CustomProviders\CustomProvider;
use App\CustomProviders\Interfaces\ISecurityAnalysis;
use App\CustomProviders\Interfaces\ICustomAuth;
use App\CustomProviders\Interfaces\IHaveHowToImplement;
use Illuminate\Support\Facades\Http;
use App\Traits\Integration\IntegrationApiTrait;

class Nexpose extends CustomProvider implements ISecurityAnalysis, ICustomAuth, IHaveHowToImplement
{
    use IntegrationApiTrait;
    private string $apiKey;
    private string $region;

    public function __construct()
    {
        parent::__construct('nexpose');
        $this->apiKey = $this->fields['api_key'];
        $this->region = $this->fields['region'];
    }

    public function attempt(array $fields): bool
    {
        $this->apiKey = $fields['api_key'];
        $this->region = $fields['region'];

        $response = $this->httpHeader()->get("https://{$this->region}.api.insight.rapid7.com/validate");

        if ($response->failed()) {
            return false;
        }

        $this->connect($this->provider, $fields);

        return true;
    }


    /**
     * This function returns the status of the last technical vulnerability scan in the last 3 months
     * Standard: ISO 27001-2-2013
     * Control: A.12.6.1,A.14.2.1,A.14.2.3,A.14.2.5,A.18.2.3
     * @return string|null
     */
    public function getTechnicalVulnerabilitiesScanStatus(): ?string
    {
        try {
            $totalVulnerability = 0;
            $criticalVulnerability = 0;
            $curPage = 0;
            do {
                $response = $this->httpHeader()
                                ->get("https://{$this->region}.api.insight.rapid7.com/ias/v1/vulnerabilities?index=".
                                $curPage."&size=50");

                $pageIndex = $response['metadata']['index'];
                $pageSize = $response['metadata']['size'];
                $total = $response['metadata']['total_data'];
                $pageVal = $total - (($pageIndex+1)*$pageSize);
                if ($response->ok()) {
                    $body = json_decode($response->body(), true);
                    $apiResponse = $body['data'] ?? $body;
                    foreach ($apiResponse as $project) {
                        if ($project['severity'] == 'HIGH') {
                            $criticalVulnerability++;
                        }
                        $totalVulnerability++;
                    }
                }
                $curPage++;
            }while($pageVal > 0);

            return json_encode([
                'passed' => $totalVulnerability - $criticalVulnerability,
                'total'=> $totalVulnerability
            ]);
        } catch (\Exception $e) {
            writeLog('error', 'Nexpose getTechnicalVulnerabilitiesScanStatus: '. $e->getMessage());
        }
        return null;
    }

    public function httpHeader()
    {
        return Http::withHeaders([
            "X-Api-Key" => $this->apiKey
        ]);
    }

    public static function getHowToImplementAction($action): ?string
    {
        $howToImplementActionsArr = [
            "getTechnicalVulnerabilitiesScanStatus" => "https://help.rapid7.com/insightappsec/en-us/api/v1/docs.html#tag/Scans/operation/get-scans",
        ];

        return array_key_exists($action, $howToImplementActionsArr) ? $howToImplementActionsArr[$action] : null;
    }
}
