<?php

namespace App\CustomProviders\Kpi;

use Illuminate\Support\Facades\Http;
use App\CustomProviders\CustomProvider;
use App\Traits\Kpi\KpiIntegrationTrait;
use App\Traits\Integration\IntegrationApiTrait;
use App\CustomProviders\Interfaces\IAssetProvider;
use App\CustomProviders\Interfaces\ICustomAuth;

class ServiceNow extends CustomProvider implements IAssetProvider, ICustomAuth
{
    private $tenantUrl;
    use IntegrationApiTrait,KpiIntegrationTrait;

    public function __construct()
    {
        parent::__construct('servicenow', false);
        $fields = $this->getFieldsValue();
        $this->tenantUrl = $fields['tenant_url'];
    }

    public function attempt(array $fields): bool
    {
        $tenantUrl = $fields['tenant_url'];
        $clientId = $fields['client_id'];
        $clientSecret = $fields['client_secret'];
        $username = $fields['username'];
        $password = $fields['password'];

        $response = Http::asForm()->post($tenantUrl . '/oauth_token.do', [
            'grant_type' => 'password',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
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
    }

    public function getProjects(): array
    {
        return [];
    }

    public function getAssets(): array
    {
        return [];
    }

    public function getIncidents(): array
    {
        return [];
    }

    private function getAuthToken(): string | null
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

    // condition: get Major Approved Change Requests
    // Logic used: Change should be Approved and Change type must be standard
    // Standard: ISO 27001-2-2013
    // control : A.12.1.2
    public function getChangeManagementFlowStatus(): ?string
    {
        $this->getAuthToken();
        try {
            $dataToReturn = [];
            $majorData = [];
            $changeTypes = ['standard', 'emergency', 'normal'];
            foreach ($changeTypes as $type) {
                if (count($dataToReturn) != 2) {
                    $response = Http::withToken($this->provider->accessToken)
                            ->get($this->tenantUrl . '/api/sn_chg_rest/change/' . $type);
                    $resp = json_decode($response->body(), true);

                    if ($response->ok() && ($resp && array_key_exists('result', $resp))) {
                        foreach ($resp['result'] as $res) {
                            if (!array_key_exists('__meta', $res) && $res['approval']['value'] == 'approved') {
                                $data = [];
                                $data['ticket_number'] = $res['sys_id']['display_value'];
                                array_push($dataToReturn, $data);
                                if ($res['type']['display_value'] === "Standard") {
                                    array_push($majorData, $data);
                                }
                            }
                        }
                    }
                }
            }
            return json_encode([
                'passed' => count($majorData),
                'total'=> count($dataToReturn)
            ]);
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with running getChangeManagementFlowStatus on ServiceNow');
        }

        return null;
    }

    // condition: check incident flow
    // logic used : Get Incidents data without sla voilation i.e made_sla = true
    // Standard: ISO 27001-2-2013
    // control : A.16.1.5
    public function getIncidentReportStatus(): ?string
    {
        $token = $this->getAuthToken();
        try {
            $dataToReturn = [];
            $dataWithoutSlaVoilation = [];

            $response = Http::withToken($token)
                ->get($this->fields['tenant_url'] . '/api/now/table/incident', [
                    'sysparm_query' => 'ORDERBYDESCsys_created_on',
                    'sysparm_fields' => 'state,category,number,short_description,description,opened_at,
                                        impact,made_sla,resolved_by,resolved_at,sys_id',
                ]);
            $resp = json_decode($response->body(), true);
            if ($response->ok() && ($resp && array_key_exists('result', $resp))) {
                foreach ($resp['result'] as $res) {
                    array_push($dataToReturn, $res);
                    if ($res['state'] === '6' && $res['category'] !== 'hardware' &&
                    $res['category'] !== '' && $res['made_sla']) {
                        array_push($dataWithoutSlaVoilation, $res);
                    }
                }
                return json_encode([
                    'passed' => count($dataWithoutSlaVoilation),
                    'total'=> count($dataToReturn)
                ]);
            }
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with running getIncidentReportStatus on ServiceNow');
        }
        return null;
    }

    public function GetLessonsLearnedIncidentReportStatus(): ?string
    {
        return null;
    }

    public function getOAuth2StatusConnection(): ?string
    {
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

    // What are assets: We take CI as Assets i.e cmdb_ci_service
    // Logic used : get all with defined asset criticality i.e busines_criticality
    // Standard: ISO 27001-2-2013
    // control : A.8.1.1
    public function getInventoryOfAssets(): ?string
    {
        try {
            $dataToReturn = [];
            $dataWithOwner = [];

            $response = $this->getAssetsData();
            if ($response->ok()) {
                $body = json_decode($response->body(), true);
                foreach ($body['result'] as $service) {
                    $data = [];
                    $data['name'] = $service['name'];
                    array_push($dataToReturn, $data);
                    if ($service['asset'] !== '' && $service['owned_by'] != '' && $service['name'] != 'Unknown' &&
                    $service['name'] != '' && $service['busines_criticality'] != '') {
                        array_push($dataWithOwner, $data);
                    }
                }
                unset($service);
            }
            unset($response);
            unset($body);

            return json_encode([
                'passed' => count($dataWithOwner),
                'total'=> count($dataToReturn)
            ]);
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with running getInventoryOfAssets on ServiceNow');
        }
        return null;
    }

    // What are assets: We take CI as Assets i.e cmdb_ci_service
    // Logic used : get all with asset owner i.e owned_by
    // Standard: ISO 27001-2-2013
    // control : A.8.1.2
    public function getOwnershipOfAssets(): ?string
    {
        try {
            $dataToReturn = [];
            $dataWithOwner = [];

            $response = $this->getAssetsData();

            if ($response->ok()) {
                $body = json_decode($response->body(), true);
                foreach ($body['result'] as $service) {
                    $data = [];
                    $data['name'] = $service['name'];
                    array_push($dataToReturn, $data);
                    if ($service['asset'] !== '' && $service['owned_by'] != '' &&
                    $service['name'] != 'Unknown' && $service['name'] != '') {
                        array_push($dataWithOwner, $data);
                    }
                }
                unset($service);
            }
            unset($response);
            unset($body);

            return json_encode([
                'passed' => count($dataWithOwner),
                'total'=> count($dataToReturn)
            ]);
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with running getOwnershipOfAssets on ServiceNow');
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

    public static function getHowToImplementAction($action): ?string
    {
        $getAssetUrl = 'https://docs.servicenow.com/bundle/quebec-servicenow-platform/page/product/configuration-management/reference/r_BusinessServiceTables.html';
        $howToImplementActionsArr = [
            "getAssets" => $getAssetUrl,
            "getChangeManagementFlowStatus" => "https://docs.servicenow.com/en-US/bundle/sandiego-it-service-management/page/product/change-management/task/t_CreateAChange.html",
            "getIncidentReportStatus" => "https://docs.servicenow.com/en-US/bundle/sandiego-it-service-management/page/product/incident-management/task/create-an-incident.html",
            "GetLessonsLearnedIncidentReportStatus" => "https://docs.servicenow.com/en-US/bundle/sandiego-it-service-management/page/product/incident-management/task/create-an-incident.html",
            "getOAuth2StatusConnection" => $getAssetUrl,
            "getInventoryOfAssets" => $getAssetUrl,
            "getOwnershipOfAssets" => $getAssetUrl,
        ];

        return array_key_exists($action, $howToImplementActionsArr) ? $howToImplementActionsArr[$action] : null;
    }
}
