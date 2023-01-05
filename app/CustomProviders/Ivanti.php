<?php

namespace App\CustomProviders;

use App\CustomProviders\Interfaces\IAssetProvider;
use App\CustomProviders\Interfaces\ICustomAuth;
use App\CustomProviders\Interfaces\IHaveHowToImplement;
use App\Traits\Integration\IntegrationApiTrait;
use App\Traits\Integration\IvantiTrait;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class Ivanti extends CustomProvider implements IAssetProvider, ICustomAuth, IHaveHowToImplement
{
    private PendingRequest  $client;
    use IntegrationApiTrait;
    use IvantiTrait;

    const API_PREFIX = 'rest_api_key=';

// Note:
// 1. ivanti only provides 100 datas per request
// 2. we take CI as assets
// 3. we take sensitivity as Criticality(decided after talk with amar)
// 4. all ivanti Apis call are similar i.e businessobject/{object} eg.businessobject/CIs
// 5. for Sla, if the response has SLA then it is false, else true(as observed by dev)
    public function __construct()
    {
        parent::__construct('ivanti');
        $tenant_url = $this->fields['tenant_url'];
        $api_key = $this->fields['api_key'];

        $this->client = Http::withHeaders([
            'Authorization' => self::API_PREFIX . $api_key,
        ])
            ->timeout(10)
            ->baseUrl($tenant_url . '/api/odata/');
    }

    public function attempt(array $fields): bool
    {
        $tenant_url = $fields['tenant_url'];
        $api_key = $fields['api_key'];

        // try to get the Projects

        try {
            $response = Http::withHeaders([
                'Authorization' => self::API_PREFIX . $api_key,
            ])
                ->get($tenant_url . '/api/odata/businessobject/Projects');

            if ($response->status() === 401) {
                return false;
            }

            $this->connect($this->provider, $fields);

            return true;
        }catch (\Throwable $e) {
            writeLog('error', 'Ivanti Attemp to connect failed: '. $e->getMessage());
            return false;
        }
    }

    public function getProjects(): array
    {
        return [];
    }

    /**
     * @throws \Illuminate\Http\Client\RequestException
     */
    // What are assets: We take CI as Assets
    // Integration: get All Assets
    // Where to find : Ivanti CI create form
    // Standard: ISO 27001-2-2013
    // control : 8.1.1
    public function getAssets(): array
    {
        try {
            $assets = $this->getAllAssetData('businessobject/CIs', false);
            if ($assets) {
                return $assets;
            }
        } catch (\Exception$th) {
            writeLog('error', 'Ivanti getAssets implementation failed: '. $th->getMessage());
            return [];
        }
        return [];
    }

    // Incidents can be made in Ivanti Create Incidents
    // Standard: ISO 27001-2-2013
    public function getIncidents(): array
    {
        try {
            $response = $this
                ->client
                ->get('businessobject/incidents?$top=5')
                ->throw();

            if ($response->status() === 204) {
                return [];
            }

            $response = json_decode($response->body(), true);

            return $response['value'];
        } catch (\Exception $e) {
            writeLog('error', 'Ivanti getIncidents implementation failed: '. $e->getMessage());
            return [];
        }
        return [];

    }

    // condition: get Approved Change Requests
    // Logic used: TypeOfChange should exist and it should be Approved
    // Standard: ISO 27001-2-2013
    // control : A.12.1.2
    public function getChangeManagementFlowStatus(): ?string
    {
        try {
            $required_values = [
                'CMApprovedBy' => true,
                'TypeOfChange' => true,
            ];
            $additional_values = [
                'ChangeNumber',
                'Subject',
                'Description',
                'CreatedDateTime',
                'RiskLevel',
                'ApprovalCondition',
            ];
            return $this->getChangeManagementFlowData('businessobject/changes', $required_values, $additional_values, false);
        } catch (\Exception $e) {
            writeLog('error', 'Ivanti getChangeManagementFlowStatus implementation failed: '. $e->getMessage());
            return null;
        }
        return null;
    }

    // condition: check incident flow
    // logic used : Get Incidents with impact and resolved
    // Standard: ISO 27001-2-2013
    // control : A.16.1.5
    public function getIncidentReportStatus(): ?string
    {
        try {
            $required_values = [
                'Impact' => true,
                'Status' => 'Resolved',
            ];
            $additional_values = [
                'IncidentNumber',
                'SLA',
                'Subject',
                'CreatedDateTime',
                'ResolvedBy',
                'ResolvedDateTime',
                'Symptom',
            ];
            return $this->incidentData('businessobject/incidents', $required_values, $additional_values, false);
        } catch (\Exception$th) {
            writeLog('error', 'Ivanti getIncidentReportStatus implementation failed: '. $th->getMessage());
            return null;
        }
        return null;
    }

    // condition: check lessons learned
    // logic used : get lesson learned from that incident(Resolution message taken as lesson learned)
    // Standard: ISO 27001-2-2013
    // control : A.16.1.6
    public function getLessonsLearnedIncidentReportStatus(): ?string
    {
        try {
            $required_values = [
                'Resolution' => true,
            ];
            $additional_values = [
                'IncidentNumber',
                'Subject',
                'CreatedDateTime',
                'Status',
                'ResolvedDateTime',
            ];
            return $this->incidentData('businessobject/incidents', $required_values, $additional_values, false);
        } catch (\Exception$e) {
            writeLog('error', 'Ivanti getLessonsLearnedIncidentReportStatus implementation failed: '. $e->getMessage());
            return null;
        }
        return null;
    }

    // What are assets: We take CI as Assets
    // Where to find : Asset Criticality is a custom field in Ivanti CI create form
    //get all assets with id,name,owner and criticality
    // Standard: ISO 27001-2-2013
    // control : A.8.1.1
    public function getInventoryOfAssets(): ?string
    {
        try {
            $assets = $this->getAllAssetData('businessobject/CIs', true);
            if ($assets) {
                return json_encode(array_slice(array_filter(array_map(function ($asset) {
                    if ($asset['type'] && $asset['name'] && $asset['owner'] && $asset['criticality']) {
                        return [
                            'name' => $asset['name'],
                            'type' => $asset['type'],
                            'owner' => $asset['owner'],
                            'criticality' => $asset['criticality'],
                        ];
                    }
                }, $assets)), 0, 3), true);
            }
        } catch (\Exception$th) {
            writeLog('error', 'Ivanti getInventoryOfAssets implementation failed: '. $th->getMessage());
            return null;
        }
        return null;
    }

    // What are assets: We take CI as Assets
    // Where to find : Asset owner is a custom field in Ivanti CI create form
    // logic used: get all CI and check if all asset owner is defined or not
    // Standard: ISO 27001-2-2013
    // control : A.8.1.2
    public function getOwnershipOfAssets(): ?string
    {
        try {
            $assets = $this->getAllAssetData('businessobject/CIs', true);
            if($assets){
                $allAssetsWithoutOwner = collect($assets)->where('owner', null)->count();
                if ($allAssetsWithoutOwner === 0) {
                return json_encode(array_slice(array_map(function ($asset) {
                    return [
                        'type' => $asset['type'],
                        'name' => $asset['name'],
                        'owner' => $asset['owner'],
                        'criticality' => $asset['criticality'],
                    ];
                }, $assets), 0, 3), true);
            }
        }
        } catch (\Exception$th) {
            writeLog('error', 'Ivanti getOwnershipOfAssets implementation failed: '. $th->getMessage());
            return null;
        }
        return null;
    }

    public static function getHowToImplementAction($action): ?string
    {
        $getAssetUrl = 'https://help.ivanti.com/ht/help/en_US/ISM/2018.3/user/Content/ServiceDesk/ConfigurationMgmt/About_Creating_a_Configuration_Item.htm';
        $howToImplementActionsArr = [
            'getAssets' => $getAssetUrl,
            'getChangeManagementFlowStatus' => 'https://help.ivanti.com/ht/help/en_US/ISM/2018.3/user/Content/ServiceDesk/Change/Creating_a_Change_Request.htm',
            'getIncidentReportStatus' => 'https://help.ivanti.com/ht/help/en_US/ISM/2020/user/Content/ServiceDesk/Incidents/Resolving-Incidents.htm',
            'GetLessonsLearnedIncidentReportStatus' => 'https://help.ivanti.com/ht/help/en_US/ISM/2020/user/Content/ServiceDesk/Incidents/Resolving-Incidents.htm',
            'getInventoryOfAssets'=>$getAssetUrl,
            'getOwnershipOfAssets'=>$getAssetUrl,
        ];

        return array_key_exists($action, $howToImplementActionsArr) ? $howToImplementActionsArr[$action] : null;
    }
}
