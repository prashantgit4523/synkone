<?php

namespace App\CustomProviders;

use App\CustomProviders\Interfaces\ISecurityAnalysis;
use App\CustomProviders\Interfaces\ICustomAuth;
use App\CustomProviders\Interfaces\IHaveHowToImplement;
use Illuminate\Support\Facades\Http;
use App\Traits\Integration\IntegrationApiTrait;
use Carbon\Carbon;

class TenableOnPrem extends CustomProvider implements ISecurityAnalysis, ICustomAuth, IHaveHowToImplement
{

    use IntegrationApiTrait;
    public function __construct()
    {
        parent::__construct('tenable-on-prem', false);

        $this->tenableUrl = $this->fields['url'];
        $this->accessKey = $this->fields['access_key'];
        $this->secretKey = $this->fields['secret_key'];
    }

    public function attempt(array $fields): bool
    {
        try {
            $response = Http::withoutVerifying()
                ->withHeaders([
                    "X-ApiKeys" => "accessKey={$fields["access_key"]}; secretKey={$fields["secret_key"]}",
                ])
                ->get($fields['url']);
            if ($response->failed()) {
                return false;
            }

            $this->connect($this->provider, $fields);
            return true;
        } catch (\Exception $e) {
            writeLog('error', 'Tanable on prem attempt to connect failed: ' . $e->getMessage());
            return false;
        }
    }

    public function getTechnicalVulnerabilitiesScanStatus(): ?string
    {
        try {
            $response = $this->httpHeader()
                ->get($this->tenableUrl . '/scans');
            if ($response->failed()) {
                return null;
            }

            $body = json_decode($response->body() ?? null, true);
            foreach ($body['scans'] as $project) {
                if (
                    array_key_exists('last_modification_date', $project) && Carbon::parse($project['last_modification_date'])
                    ->diffInMonths() < 3
                ) {
                    $projects[] = $project;
                }
            }
            return json_encode($this->formatResponse(
                $projects,
                [],
                ['uuid', 'owner', 'name', 'last_modification_date'],
                '='
            ));
        } catch (\Exception $e) {
            writeLog('error', 'Tanable on prem getTechnicalVulnerabilitiesScanStatus implementation failed: ' . $e->getMessage());
            return null;
        }
    }

    public static function getHowToImplementAction($action): ?string
    {
        $howToImplementActionsArr = [
            "getTechnicalVulnerabilitiesScanStatus" => "",
        ];
        return array_key_exists($action, $howToImplementActionsArr) ? $howToImplementActionsArr[$action] : null;
    }

    public function httpHeader()
    {
        return Http::withoutVerifying()
            ->withHeaders([
                "X-ApiKeys" => "accessKey={$this->accessKey}; secretKey={$this->secretKey}",
            ]);
    }
}
