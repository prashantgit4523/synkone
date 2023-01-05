<?php

namespace App\CustomProviders;

use App\CustomProviders\Interfaces\IAssetProvider;
use App\CustomProviders\Interfaces\ICustomAuth;
use App\Traits\Integration\serviceNowGetAssetsTrait;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class ServiceNow extends CustomProvider implements IAssetProvider, ICustomAuth
{

    private $tenantUrl;
    use serviceNowGetAssetsTrait;

    private $incidentUrl = "/api/now/table/incident";

    public function __construct()
    {
        parent::__construct('servicenow', false);
        $fields = $this->getFieldsValue();
        $this->tenantUrl = $fields['tenant_url'];
    }

    public function attempt(array $fields): bool
    {
        $tenant_url = $fields['tenant_url'];
        $client_id = $fields['client_id'];
        $client_secret = $fields['client_secret'];
        $username = $fields['username'];
        $password = $fields['password'];

        try {
            $response = Http::asForm()->post($tenant_url . '/oauth_token.do', [
                'grant_type' => 'password',
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'username' => $username,
                'password' => $password,
            ]);

            $body = json_decode($response->body(), true);

            if ($response->status() === 401 || !$body) {
                return false;
            }

            $this->connect(
                $this->provider,
                collect($fields)->only(['tenant_url', 'client_id', 'client_secret'])->toArray(),
                [
                    'accessToken' => $body['access_token'],
                    'refreshToken' => $body['refresh_token'],
                    'tokenExpires' => $body['expires_in'],
                ]
            );

            return true;
        } catch (\Throwable $e) {
            writeLog('error', 'ServiceNow attemp connect failed: '. $e->getMessage());
            return false;
        }
    }

    public function getProjects(): array
    {
        return [];
    }

    public function getAssets(): array
    {
        try {
            $token = $this->getAuthToken();
            if (!$token) {
                return [];
            }
            $data_to_send = [];
            $asset_array = [];
            $not_set = 'Not Set';
            //get all assets from service api
            $response = Http::withToken($token)
                ->get($this->fields['tenant_url'] . '/api/now/table/cmdb_ci_service', [
                    'sysparm_fields' => 'short_description,busines_criticality,asset',
                    'sysparm_exclude_reference_link' => 'true',
                ]);
            if ($response->ok()) {
                $body = json_decode($response->body(), true);
                foreach ($body['result'] as $service) {
                    if ($service['asset'] !== '' && !in_array($service['asset'], $asset_array, true)) {
                        array_push($asset_array, $service['asset']);
                        $service_data = [
                            'busines_criticality' => $service['busines_criticality'] === '' ? $not_set
                                                    : $service['busines_criticality'],
                            'short_description' => $service['short_description'] === '' ? $not_set
                                                    : $service['short_description'],
                        ];
                        //get asset
                        $response_asset = Http::withToken($token)
                            ->get($this->fields['tenant_url'] . '/api/now/table/alm_asset/' . $service['asset'], [
                                'sysparm_fields' => 'sys_id,display_name,model_category,comments,owned_by',
                                'sysparm_display_value' => 'true',
                                'sysparm_exclude_reference_link' => 'true',
                            ]);

                        if ($response->ok()) {
                            $asset = json_decode($response_asset->body(), true)['result'];
                            $asset_data = $this->assetReturn($asset, $service_data);
                            array_push($data_to_send, $asset_data);
                        }
                    }
                }
                unset($service);
            }
            unset($response);
            unset($body);

            $response = Http::withToken($token)
                ->get($this->fields['tenant_url'] . '/api/now/table/alm_asset', [
                    'sysparm_fields' => 'sys_id,display_name,model_category,comments,owned_by,ci,sys_created_on',
                    'sysparm_display_value' => 'true',
                    'sysparm_exclude_reference_link' => 'true',
                ]);
            if ($response->ok()) {
                $body = json_decode($response->body(), true);
                foreach ($body['result'] as $asset) {
                    if (!in_array($asset['sys_id'], $asset_array)) {
                        $service_data = [
                            'busines_criticality' => $not_set,
                            'short_description' => $not_set,
                        ];
                        $asset_data = $this->assetReturn($asset, $service_data);
                        array_push($data_to_send, $asset_data);
                    }
                }
                unset($asset);
            }
            return $data_to_send;
        } catch (\Exception $e) {
            writeLog('error', 'ServiceNow getAssets has an issue: '. $e->getMessage());
            return [];
        }
    }

    private function getAuthToken(): string|null
    {
        $token = $this->provider->accessToken;

        $response = Http::withToken($this->provider->accessToken)
            ->get($this->fields['tenant_url'] . '/api/now/branding');

        if ($response->successful()) {
            return $token;
        }

        $response = Http::asForm()->post($this->fields['tenant_url'] . '/oauth_token.do', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->provider->refreshToken,
            'client_id' => $this->fields['client_id'],
            'client_secret' => $this->fields['client_secret'],
        ]);

        if ($response->failed()) {
            return null;
        }

        $body = json_decode($response->body(), true);

        $this->provider->update(['accessToken' => $body['access_token']]);

        return $body['access_token'];
    }

    // condition: get all Change Requests
    // Logic used: first two approved changes are taken
    // Standard: ISO 27001-2-2013
    // control : A.12.1.2
    public function getChangeManagementFlowStatus(): ?string
    {
        $this->getAuthToken();
        try {
            $data_to_return = [];
            $changeTypes = ['standard', 'emergency', 'normal'];
            foreach ($changeTypes as $type) {
                if (count($data_to_return) != 2) {
                    $response = Http::withToken($this->provider->accessToken)
                        ->get($this->tenantUrl . '/api/sn_chg_rest/change/' . $type);

                    $resp = json_decode($response->body(), true);
                    if ($response->ok() && ($resp && array_key_exists('result', $resp))) {
                        foreach ($resp['result'] as $res) {
                            if (!array_key_exists('__meta', $res) && $res['approval']['value'] == 'approved') {
                                $data = [];
                                $data['ticket_number'] = $res['sys_id']['display_value'];
                                $data['title'] = $res['short_description']['display_value'];
                                $data['description'] = $res['description']['display_value'];
                                $data['date_time'] = $res['sys_updated_on']['display_value'];
                                $data['type'] = $res['type']['display_value'];
                                $data['approval_set'] = $res['assignment_group']['display_value'];
                                $data['approval'] = $res['approval']['display_value'];
                                $data['risk'] = $res['risk_impact_analysis']['display_value'];
                                array_push($data_to_return, $data);
                            }
                            if (count($data_to_return) > 1) {
                                break;
                            }
                        }
                    }
                }
            }

            if (count($data_to_return)) {
                return json_encode($data_to_return);
            }
        } catch (\Exception$e) {
            writeLog('error', 'ServiceNow getChangeManagementFlowStatus implementation failed: '.$e->getMessage());
            return null;
        }

        return null;
    }

    // condition: check incident flow
    // logic used : Get first three resolved Incidents data except from hardware category and show made_sla information
    // Standard: ISO 27001-2-2013
    // control : A.16.1.5
    public function getIncidentReportStatus(): ?string
    {
        $this->getAuthToken();
        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get($this->tenantUrl . $this->incidentUrl, [
                    'sysparm_query' => 'ORDERBYDESCsys_created_on',
                    'sysparm_fields' => 'state,category,number,short_description,description,
                                        opened_at,impact,made_sla,resolved_by,resolved_at,sys_id',
                ]);
            $resp = json_decode($response->body(), true);
            if ($response->ok() && ($resp && array_key_exists('result', $resp))) {
                $data_to_return = [];
                $filtered_data = [];
                foreach ($resp['result'] as $res) {
                    if ($res['state'] === '6' && $res['category'] !== 'hardware' && $res['category'] !== '') {
                        $data = [
                            'ticket_number' => $res['number'],
                            'short_description' => $res['short_description'],
                            'description' => $res['description'] !== '' ? $res['description'] : '',
                            'opened_at' => $res['opened_at'],
                            'impact' => $this->getImpactValueById($res['impact']),
                            'made_sla' => $res['made_sla'],
                            'resolved_by' => $res['resolved_by'],
                            'state' => 'Resolved',
                            'resolved_at' => $res['resolved_at'],
                        ];
                        array_push($data_to_return, $data);
                    }
                    if (count($data_to_return) > 2) {
                        break;
                    }
                }
                foreach ($data_to_return as $data) {
                    $response = Http::withToken($this->provider->accessToken)
                        ->get($data['resolved_by']['link']);
                    $body = json_decode($response->body(), true);
                    $filtered_data[] = [
                        'ticket_number' => $data['ticket_number'],
                        'short_description' => $data['short_description'],
                        'description' => $data['description'],
                        'opened_at' => $data['opened_at'],
                        'impact' => $data['impact'],
                        'made_sla' => $data['made_sla'],
                        'resolved_by' => $body['result']['name'],
                        'state' => $data['state'],
                        'resolved_at' => $data['resolved_at']
                    ];
                }
                if (count($filtered_data)) {
                    return json_encode($filtered_data);
                }
            }
        } catch (\Exception$e) {
            writeLog('error', 'ServiceNow getIncidentReportStatus implementation failed: '.$e->getMessage());
            return null;
        }
        return null;
    }

    // condition: check incident flow
    // logic used : Get first three Incidents data except from hardware category and show close_notes as lesson learned
    // Standard: ISO 27001-2-2013
    // control : A.16.1.6
    public function GetLessonsLearnedIncidentReportStatus(): ?string
    {
        $this->getAuthToken();
        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get($this->tenantUrl . $this->incidentUrl, [
                    'sysparm_query' => 'ORDERBYDESCsys_created_on',
                    'sysparm_fields' => 'state,category,close_notes,number,short_description,opened_at,sys_id',
                ]);
            $resp = json_decode($response->body(), true);

            if ($response->ok() && ($resp && array_key_exists('result', $resp))) {
                $data_to_return = [];
                foreach ($resp['result'] as $res) {
                    if ($res['state'] === '6' && $res['category'] !== 'hardware' && $res['category'] !== ''
                    && $res['close_notes'] !== '') {
                        $data = [
                            'ticket_number' => $res['number'],
                            'title' => $res['short_description'],
                            'date_time' => $res['opened_at'],
                            'state' => 'Resolved',
                            'lesson_learned' => $res['close_notes'],
                        ];
                        array_push($data_to_return, $data);
                    }
                    if (count($data_to_return) > 2) {
                        break;
                    }
                }
                if (count($data_to_return)) {
                    return json_encode($data_to_return);
                }
            }
        } catch (\Exception$e) {
            writeLog('error', 'ServiceNow GetLessonsLearnedIncidentReportStatus implementation failed: '.$e->getMessage());
            return null;
        }
        return null;
    }

    public function getFieldsValue()
    {
        $fieldsValue = $this->fields;
        $data = [];
        $data['tenant_url'] = $fieldsValue['tenant_url'];
        return $data;
    }

    private function getImpactValueById($id)
    {
        switch ($id) {
            case 1:
                return 'High';

            case 2:
                return 'Medium';

            default:
                return 'Low';
        }
    }

    public static function getHowToImplementAction($action): ?string
    {
        $getAssetUrl = 'https://docs.servicenow.com/bundle/quebec-servicenow-platform/page/product/configuration-management/reference/r_BusinessServiceTables.html';
        $howToImplementActionsArr = [
            "getAssets" => $getAssetUrl,
            "getChangeManagementFlowStatus" => "https://docs.servicenow.com/en-US/bundle/sandiego-it-service-management/page/product/change-management/task/t_CreateAChange.html",
            "getIncidentReportStatus" => "https://docs.servicenow.com/en-US/bundle/sandiego-it-service-management/page/product/incident-management/task/create-an-incident.html",
            "GetLessonsLearnedIncidentReportStatus" => "https://docs.servicenow.com/en-US/bundle/sandiego-it-service-management/page/product/incident-management/task/create-an-incident.html",
            "getInventoryOfAssets" => $getAssetUrl,
            "getOwnershipOfAssets" => $getAssetUrl,
        ];

        return array_key_exists($action, $howToImplementActionsArr) ? $howToImplementActionsArr[$action] : null;
    }

    // What are assets: We take CI as Assets i.e cmdb_ci_service
    // Logic used : get first five ci assets with owner and show asset criticality i.e busines_criticality
    // Standard: ISO 27001-2-2013
    // control : A.8.1.1
    public function getInventoryOfAssets(): ?string
    {
        try {
            $assets = [];
            $data = [];

            $response = $this->getAssetsData();
            if ($response->ok()) {
                $body = json_decode($response->body(), true);
                foreach ($body['result'] as $service) {
                    if ($service['asset'] !== '' && $service['owned_by'] != '' && $service['name'] != 'Unknown'
                    && $service['name'] != '') {
                        $assets[] = [
                            'name' => $service['name'],
                            'type' => substr($service['sys_class_name'], 8),
                            'owner' => $service['owned_by'],
                            'criticality' => $service['busines_criticality']
                        ];
                        //count asset
                        if (count($assets) > 4) {
                            break;
                        }
                    }
                }
                unset($service);
            }
            unset($response);
            unset($body);

            foreach ($assets as $asset) {
                $response = Http::withToken($this->provider->accessToken)
                    ->get($asset['owner']['link']);
                $body = json_decode($response->body(), true);
                $data[] = [
                    'name' => $asset['name'],
                    'type' => $asset['type'],
                    'owner' => $body['result']['name'],
                    'criticality' => $asset['criticality']
                ];
            }

            if (count($data)) {
                return json_encode($data);
            }
        } catch (\Exception $e) {
            writeLog('error', 'ServiceNow getInventoryOfAssets implementation failed: '.$e->getMessage());
            return null;
        }
        return null;
    }

    // What are assets: We take CI as Assets i.e cmdb_ci_service
    // Logic used : get first five with asset owner and if any of the asset doesnot
    // have owner then control is not implemented
    // Standard: ISO 27001-2-2013
    // control : A.8.1.2
    public function getOwnershipOfAssets(): ?string
    {
        try {
            $assets = [];
            $data = [];

            $response = $this->getAssetsData();
            if ($response->ok()) {
                $body = json_decode($response->body(), true);
                foreach ($body['result'] as $service) {
                    if ($service['asset'] !== '' && $service['owned_by'] != '' && $service['name'] != 'Unknown'
                    && $service['name'] != '') {
                        $assets[] = [
                            'name' => $service['name'],
                            'type' => substr($service['sys_class_name'], 8),
                            'owner' => $service['owned_by'],
                            'criticality' => $service['busines_criticality']
                        ];
                    }else {
                        return null;
                    }
                }
                unset($service);
            }
            unset($response);
            unset($body);

            foreach ($assets as $asset) {
                $finalAssets[] = $asset;
                if (count($finalAssets) > 4) {
                    break;
                }
            }

            foreach ($finalAssets as $asset) {
                $response = Http::withToken($this->provider->accessToken)
                    ->get($asset['owner']['link']);
                $body = json_decode($response->body(), true);
                $data[] = [
                    'name' => $asset['name'],
                    'type' => $asset['type'],
                    'owner' => $body['result']['name'],
                    'criticality' => $asset['criticality']
                ];
            }
            if (count($data)) {
                return json_encode($data);
            }
        } catch (\Exception $e) {
            writeLog('error', 'ServiceNow getInventoryOfAssets implementation failed: '.$e->getMessage());
            return null;
        }
        return null;
    }

    private function getAssetsData()
    {
        $token = $this->getAuthToken();

        return Http::withToken($token)
        ->get($this->fields['tenant_url'] . '/api/now/table/cmdb_ci_service', [
            'sysparm_fields' => 'name,sys_class_name,owned_by,busines_criticality,asset,sys_id',
            'sysparm_exclude_reference_link' => true
        ]);
    }
}
