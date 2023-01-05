<?php

namespace App\CustomProviders;

use App\CustomProviders\Interfaces\IAssetProvider;
use App\CustomProviders\Interfaces\ICustomAuth;
use App\CustomProviders\Interfaces\IHaveHowToImplement;
use App\Traits\Integration\ManageEngineCloudApiTrait;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;

class ManageEngineCloud extends CustomProvider implements ICustomAuth ,IAssetProvider,IHaveHowToImplement
{
    private PendingRequest $client;
    use ManageEngineCloudApiTrait;


    public function __construct()
    {
        parent::__construct('manage-engine-cloud',false);
        $this->client = Http::asForm()
            ->withHeaders([
                'Authorization' => 'Zoho-Oauthtoken ' . $this->getAuthToken() ?? '',
                'Accept' => 'application/vnd.manageengine.sdp.v3+json',
            ])
            ->baseUrl('https://sdpondemand.manageengine.com/api/v3');
        $this->assets = $this->getAllAssets() ?? [];
        $this->incidents = $this->getAllIncidents() ?? [];
    }

    public function attempt(array $fields): bool
    {
        return false;
    }

    public function getProjects(): array
    {
        return [];
    }

    // What are assets: We take Cmdb as Assets
    // Integration: get All Assets
    // Where to find : cmdb
    // Standard: ISO 27001-2-2013
    public function getAssets(): array
    {
        try {
            if(count($this->assets) > 0){
                return $this->assets;
            }
        } catch (\Exception$th) {
            writeLog('error', 'ManageEngineCloud getAssets implementation failed: '.$th->getMessage());
            return [];
        }
        return [];
    }

    // What are assets: We take Cmdb as Assets
    // Where to find : cmdb
    // logic used: get where we have these Asset name,Asset type,Asset owner,Asset criticality
    // Standard: ISO 27001-2-2013
    // control : A.8.1.1
    public function getInventoryOfAssets(): ?string
    {
        try {
            if(count($this->assets) > 0){
                return json_encode(array_slice(array_filter(array_map(function ($asset) {
                    if ($asset['type'] && $asset['name'] && $asset['owner'] && $asset['classification']) {
                        return [
                            'name' => $asset['name'],
                            'type'=> $asset['type'],
                            'owner' => $asset['owner'],
                            'criticality' => $asset['classification'],
                        ];
                    }
                }, $this->assets)), 0, 3),true);
            }
        } catch (\Exception$th) {
            writeLog('error', 'ManageEngineCloud getInventoryOfAssets implementation failed: '.$th->getMessage());
            return null;
        }
        return null;
    }

    // What are assets: We take Cmdb as Assets
    // Where to find : cmdb
    // logic used: get all assets and check if asset owner is defined or not
    // Standard: ISO 27001-2-2013
    // control : A.8.1.2
    public function getOwnershipOfAssets(): ?string
    {
        try {
            if(count($this->assets) > 0){
                $allAssetsWithoutOwner = collect($this->assets)->where('owner', null)->count();
                if ($allAssetsWithoutOwner === 0) {
                    return json_encode(array_slice(array_map(function ($asset) {
                        return [
                            'name' => $asset['name'],
                            'type'=> $asset['type'],
                            'owner' => $asset['owner'],
                            'criticality' => $asset['classification'],
                        ];
                    }, $this->assets), 0, 3),true);
                }
            }
        } catch (\Exception$th) {
            writeLog('error', 'ManageEngineCloud getOwnershipOfAssets implementation failed: '.$th->getMessage());
            return null;
        }
        return null;
    }

    // condition: get Approved Change Requests
    // Logic used: change_type should exist and it should be Approved
    // Standard: ISO 27001-2-2013
    // control : A.12.1.2
    public function getChangeManagementFlowStatus(): ?string
    {
        try {
            $changes = $this->getAllChanges();
            if(count($changes) > 0){
                return json_encode(array_slice($changes, 0, 3),true);
            }
        } catch (\Exception$th) {
            writeLog('error', 'ManageEngineCloud getChangeManagementFlowStatus implementation failed: '.$th->getMessage());
            return null;
        }
        return null;
    }

    private function getAuthToken(): string | null
    {
        try {
            Socialite::driver('manage-engine-cloud')->userFromToken($this->provider->accessToken);
            return $this->provider->accessToken;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
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

    // condition: check incident flow
    // logic used : Get Incidents with impact and resolved
    // Standard: ISO 27001-2-2013
    // control : A.16.1.5
    public function getIncidentReportStatus(): ?string
    {
        try {
            if(count($this->incidents) > 0){
                return json_encode(array_slice(array_map(function ($incident) {
                    return [
                        'id' => $incident['id'],
                        'subject' => $incident['subject'],
                        'description' => $incident['description'],
                        'created_time' => $incident['created_time'],
                        'request_type' => $incident['request_type'],
                        'requester' => $incident['requester'],
                        'category' => $incident['category'],
                        'impact' => $incident['impact'],
                        'sla' => $incident['sla'],
                        'resolved_time' => $incident['resolved_time'],
                    ];
                }, $this->incidents), 0, 3),true);
            }
        } catch (\Exception$e) {
            writeLog('error', 'ManageEngineCloud getIncidentReportStatus implementation failed: '.$e->getMessage());
        }
        return null;
    }

    // condition: check lessons learned
    // logic used : get lesson learned from that incident(Resolution message taken as lesson learned)
    // Standard: ISO 27001-2-2013
    // control : A.16.1.6
    public function GetLessonsLearnedIncidentReportStatus(): ?string
    {
        try {
            if(count($this->incidents) > 0){
                return json_encode(array_slice(array_filter($this->incidents,function($incident){
                    if($incident['resolution']){
                        return true;
                    }
                }), 0, 3),true);
            }
        } catch (\Exception $e) {
            writeLog('error', 'ManageEngineCloud GetLessonsLearnedIncidentReportStatus implementation failed: '.$e->getMessage());
            return null;
        }
        return null;
    }

    public static function getHowToImplementAction($action): ?string
    {
        $createAssetUrl ='https://www.manageengine.com/products/service-desk-msp/help/adminguide/cmdb/cmdb_listview.html';
        $close_request = 'https://help.sdpondemand.com/closing-a-request';
        $howToImplementActionsArr = [
            'getAssets'=> $createAssetUrl,
            'getInventoryOfAssets'=>$createAssetUrl,
            'getOwnershipOfAssets'=> $createAssetUrl,
            'getChangeManagementFlowStatus'=> 'https://help.sdpondemand.com/change-list-view',
            'getIncidentReportStatus'=> $close_request,
            'GetLessonsLearnedIncidentReportStatus'=> $close_request,
        ];

        return array_key_exists($action, $howToImplementActionsArr) ? $howToImplementActionsArr[$action] : null;
    }
}
