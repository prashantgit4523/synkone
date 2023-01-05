<?php

namespace App\CustomProviders;

use App\CustomProviders\Interfaces\IAssetProvider;
use App\CustomProviders\Interfaces\ICustomAuth;
use App\CustomProviders\Interfaces\IHaveHowToImplement;
use App\Traits\Integration\IntegrationApiTrait;
use App\Traits\Integration\ManageEngineOnPremTrait;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class ManageEngineOnPrem extends CustomProvider implements IAssetProvider, ICustomAuth, IHaveHowToImplement
{
    private PendingRequest $client;
    use ManageEngineOnPremTrait, IntegrationApiTrait;

    public function __construct()
    {
        parent::__construct('manage-engine-on-prem');

        $this->url = $this->fields['url'];
        $this->api_key = $this->fields['api_key'];

        $this->client = Http::withHeaders([
            'TECHNICIAN_KEY' => $this->api_key,
        ])
            ->timeout(10)
            ->baseUrl($this->url . '/api');
        $this->assets = $this->getAllAssets();
        $this->incidents = $this->getAllIncidents();
    }

    public function attempt(array $fields): bool
    {
        if (
            Http::timeout(5)
            ->withHeaders(['TECHNICIAN_KEY' => $fields['api_key']])
            ->baseUrl($this->getBaseUrl($fields['url']))
            ->get('/api/v3/users')
            ->failed()
        ) {
            return false;
        }

        $this->connect($this->provider, $fields);
        return true;
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
            if($this->assets){
                return $this->assets;
            }
        } catch (\Exception $th) {
            writeLog('error', 'ManageEngineOnPrem getAssets implementation failed: '.$th->getMessage());
            return [];
        }
        return [];
    }

    // condition: check incident flow
    // logic used : Get Incidents with impact and resolved
    // Standard: ISO 27001-2-2013
    // control : A.16.1.5
    public function getIncidentReportStatus(): ?string
    {
        try {
            if ($this->incidents) {
                return json_encode(
                    array_slice(
                        array_map(
                            function ($incident) {
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
                            },
                            $this->incidents
                        ),
                        0,
                        3
                    ),
                    true
                );
            }
        } catch (\Exception $e) {
            writeLog('error', 'ManageEngineOnPrem getIncidentReportStatus implementation failed: '.$e->getMessage());
            return null;
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
            if ($this->incidents) {
                return json_encode(
                    array_slice(
                        array_filter(
                            $this->incidents,
                            function ($incident) {
                                if ($incident['resolution']) {
                                    return true;
                                }
                            }
                        ),
                        0,
                        3
                    ),
                    true
                );
            }
        } catch (\Exception $e) {
            writeLog('error', 'ManageEngineOnPrem GetLessonsLearnedIncidentReportStatus implementation failed: '.$e->getMessage());
            return $e;
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
            if ($changes) {
                return json_encode(array_slice($changes, 0, 3), true);
            }
        } catch (\Exception $th) {
            writeLog('error', 'ManageEngineOnPrem getChangeManagementFlowStatus implementation failed: '.$th->getMessage());
            return null;
        }
        return null;
    }


    // What are assets: We take Cmdb as Assets
    // Where to find : cmdb
    // logic used: get where we have these Asset name,Asset type,Asset owner,Asset criticality
    // Standard: ISO 27001-2-2013
    // control : A.8.1.1
    public function getInventoryOfAssets(): ?string
    {
        try {
            if ($this->assets) {
                return json_encode(
                    array_slice(
                        array_filter(
                            array_map(
                                function ($asset) {
                                    if (
                                        $asset['type'] && $asset['name'] &&
                                        $asset['owner'] && $asset['classification']
                                    ) {
                                        return [
                                            'name' => $asset['name'],
                                            'type' => $asset['type'],
                                            'owner' => $asset['owner'],
                                            'criticality' => $asset['classification'],
                                        ];
                                    }
                                },
                                $this->assets
                            )
                        ),
                        0,
                        3
                    ),
                    true
                );
            }
        } catch (\Exception $th) {
            writeLog('error', 'ManageEngineOnPrem getInventoryOfAssets implementation failed: '.$th->getMessage());
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
            if ($this->assets) {
                $allAssetsWithoutOwner = collect($this->assets)->where('owner', null)->count();
                if ($allAssetsWithoutOwner === 0) {
                    return json_encode(
                        array_slice(
                            array_map(
                                function ($asset) {
                                    return [
                                        'name' => $asset['name'],
                                        'type' => $asset['type'],
                                        'owner' => $asset['owner'],
                                        'criticality' => $asset['classification'],
                                    ];
                                },
                                $this->assets
                            ),
                            0,
                            3
                        ),
                        true
                    );
                }
            }
        } catch (\Exception $th) {
            writeLog('error', 'ManageEngineOnPrem getOwnershipOfAssets implementation failed: '.$th->getMessage());
            return null;
        }
        return null;
    }

    public static function getHowToImplementAction($action): ?string
    {
        $baseUrl =  'https://www.manageengine.com/products/service-desk-msp/help/adminguide/';
        $assetUrl = $baseUrl . 'assets/assets/it_assets/adding_it_assets.html';
        $incidentClose = $baseUrl . 'requests/close-request.html';
        $howToImplementActionsArr = [
            'getAssets' => $assetUrl,
            'getInventoryOfAssets' => $assetUrl,
            'getOwnershipOfAssets' => $assetUrl,
            'getChangeManagementFlowStatus' => $baseUrl . 'change/creating_new_change.html',
            'getIncidentReportStatus' => $incidentClose,
            'GetLessonsLearnedIncidentReportStatus' => $incidentClose,
        ];

        return array_key_exists($action, $howToImplementActionsArr) ? $howToImplementActionsArr[$action] : null;
    }
}
