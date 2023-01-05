<?php

namespace App\CustomProviders\Kpi;

use App\CustomProviders\CustomProvider;
use App\CustomProviders\Interfaces\ICustomAuth;
use App\CustomProviders\Interfaces\IHaveHowToImplement;
use App\Traits\Integration\IntegrationApiTrait;
use App\Traits\Integration\ManageEngineCloudApiTrait;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;

class ManageEngineCloud extends CustomProvider implements ICustomAuth, IHaveHowToImplement
{
    private PendingRequest $client;
    use IntegrationApiTrait;
    use ManageEngineCloudApiTrait;

    public function __construct()
    {
        parent::__construct('manage-engine-cloud');
        $this->client = Http::asForm()
            ->withHeaders([
                'Authorization' => 'Zoho-Oauthtoken ' . $this->getAuthToken() ?? '',
                'Accept' => 'application/vnd.manageengine.sdp.v3+json',
            ])
            ->baseUrl('https://sdpondemand.manageengine.com/api/v3');
        $this->assets = $this->getAllAssets();
        $this->incidents = $this->getAllIncidents();
    }

    public function attempt(array $fields): bool
    {
        return false;
    }

    private function getAuthToken(): string | null
    {
        try {
            Socialite::driver('manage-engine-cloud')->userFromToken($this->provider->accessToken);
            return $this->provider->accessToken;
        } catch (\GuzzleHttp\Exception\ClientException$e) {
            // something went wrong, try to refresh
            $response = Http::asForm()->post('https://accounts.zoho.com/oauth/v2/token', [
                'grant_type' => 'refresh_token',
                'refresh_token' => $this->provider->refreshToken,
                'client_id' => env('ZOHO_CLIENT_ID'),
                'client_secret' => env('ZOHO_CLIENT_SECRET'),
            ]);

            if ($response->failed()) {
                return null;
            }

            $access_token = json_decode($response->body(), true)['access_token'];

            $this->provider->update(['accessToken' => $access_token]);

            return $access_token;
        }
    }

    /**
     * @throws \Illuminate\Http\Client\RequestException
     */
    // What are assets: We take Cmdb as Assets
    // KPI: number of assets with defined asset criticality
    // Where to find : cmdb
    // Standard: ISO 27001-2-2013
    // control : A.8.1.1
    // Impact is taken as Criticality
    public function getInventoryOfAssets(): ?string
    {
        try {
            if (count($this->assets)) {
                $total = count($this->assets);
                $passed = count(array_filter($this->assets, function ($asset) {
                    if ($asset['classification']) {
                        return true;
                    }
                }));
                return json_encode([
                    'passed' => $passed,
                    'total' => $total,
                ]);
            }
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with running getInventoryOfAssets on ManageEngine Cloud');
        }
        return null;
    }

    // What are assets: We take Cmdb as Assets
    // KPI: number of assets with defined asset owner
    // Where to find : cmdb
    // Standard: ISO 27001-2-2013
    // control : A.8.1.2
    public function getOwnershipOfAssets(): ?string
    {
        try {
            if (count($this->assets)) {
                $total = count($this->assets);
                $passed = count(array_filter($this->assets, function ($asset) {
                    if ($asset['owner']) {
                        return true;
                    }
                }));
                return json_encode([
                    'passed' => $passed,
                    'total' => $total,
                ]);
            }
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with running getOwnershipOfAssets on ManageEngine Cloud');
        }
        return null;
    }

    // KPI: Number of major changes approved by information security.
    // Logic used: change_type should exist and it should be Approved
    // Standard: ISO 27001-2-2013
    // control : A.12.1.2
    //skip as directed by amar
    public function getChangeManagement(): ?string
    {
        return null;
    }

    // KPI: number of incidents resolved on time without any SLA violation.
    // (number of incidents without response time violation.)
    // (no violation approach taken as true.)
    // logic used:
    // SLAs = "High SLA","Low SLA",'Normal SLA',"Medium SLA"
    // note: the incident has to be resolved too
    // is_overdue is taken as SLA breach
    // Standard: ISO 27001-2-2013
    // control : A.16.1.5
    public function getIncidentReportStatus()
    {
        try {
            if(count($this->incidents)){
                $total = count($this->incidents);
                $passed = count(array_filter($this->incidents, function ($incident) {
                    if (!$incident['time_voilation']) {
                        return true;
                    }
                }));
                return json_encode([
                    'passed' => $passed,
                    'total' => $total,
                ]);
            }
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with running getIncidentReportStatus on ManageEngine Cloud');
        }
        return null;
    }

    public static function getHowToImplementAction($action): ?string
    {
        $createAssetUrl = 'https://www.manageengine.com/products/service-desk-msp/help/adminguide/cmdb/cmdb_listview.html';
        $howToImplementActionsArr = [
            'getInventoryOfAssets' => $createAssetUrl,
            'getOwnershipOfAssets' => $createAssetUrl,
            'getChangeManagement' => 'https://help.sdpondemand.com/change-list-view',
            'getResponseToInformationSecurityIncidents' => 'https://help.sdpondemand.com/closing-a-request',
        ];

        return array_key_exists($action, $howToImplementActionsArr) ? $howToImplementActionsArr[$action] : null;
    }
}
