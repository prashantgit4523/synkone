<?php

namespace App\CustomProviders\Kpi;

use App\CustomProviders\CustomProvider;
use App\CustomProviders\Interfaces\ICustomAuth;
use App\CustomProviders\Interfaces\IHaveHowToImplement;
use App\Traits\Integration\IntegrationApiTrait;
use App\Traits\Integration\IvantiTrait;
use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class Ivanti extends CustomProvider implements ICustomAuth, IHaveHowToImplement
{
    private PendingRequest $client;
    use IntegrationApiTrait;
    use IvantiTrait;

    const API_PREFIX = 'rest_api_key=';

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
        $response = Http::withHeaders([
            'Authorization' => self::API_PREFIX . $api_key,
        ])
            ->get($tenant_url . '/api/odata/businessobject/Projects');

        if ($response->status() === 401) {
            return false;
        }

        $this->connect($this->provider, $fields);
        return true;
    }
    /**
     * @throws \Illuminate\Http\Client\RequestException
     */
    // What are assets: We take CI as Assets
    // KPI: number of assets with defined asset criticality
    // Where to find : Asset Criticality is a custom field in Ivanti CI create form
    // logic used: get all CI and check if asset criticality is defined or not
    // Standard: ISO 27001-2-2013
    // control : A.8.1.1
    public function getInventoryOfAssets(): ?string
    {
        try {
            $assets = $this->getAllAssetData('businessobject/CIs', true);
            return json_encode([
                'passed' => count(array_filter($assets, function ($asset) {
                    return $asset['criticality'] !== null;
                })),
                'total' => count($assets),
            ]);
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with getInventoryOfAssets on Ivanti');
        }
        return null;
    }

    // What are assets: We take CI as Assets
    // KPI: number of assets with defined asset owner
    // Where to find : Asset owner is a custom field in Ivanti CI create form
    // logic used: get all CI and check if asset owner is defined or not
    // Standard: ISO 27001-2-2013
    // control : A.8.1.2
    public function getOwnershipOfAssets(): ?string
    {
        try {
            $assets = $this->getAllAssetData('businessobject/CIs', true);
            return json_encode([
                'passed' => count(array_filter($assets, function ($asset) {
                    return $asset['owner'] !== null;
                })),
                'total' => count($assets),
            ]);
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with getOwnershipOfAssets on Ivanti');
        }
        return null;
    }

    // KPI: Number of major changes approved by information security.
    // Number of resolved changes.
    // Number of rejected changes.
    // on hold as per Amar(we need to make some decision on how to implemet this in real world situation)
    public function getChangeManagement(): ?string
    {
        return null;
    }

    // What are assets: We take CI as Assets
    // KPI: number of incidents resolved on time without any SLA violation.
    // (number of incidents without response time violation.)
    // (no volation approach taken as true.)
    // Where to find : SLA is determined by the response time taken to Resolve the incident
    // logic used: In Incident Response, we have a field called SLA based on comparing the response time and breach time
    // (2 diagrams found on top of single incident view) I decided to Take SLA as when null as no voilation
    // note: the incident has to be resolved too
    // Standard: ISO 27001-2-2013
    // control : A.16.1.5
    public function getIncidentReportStatus(): ?string
    {
        try {
            $required_values = [
                'Status' => 'Resolved',
            ];
            $additional_values = [
                'SLA',
                'ResolvedBy',
                'ResolvedDateTime',
            ];
            $incidents = $this->incidentData('businessobject/incidents', $required_values, $additional_values, true);
            return json_encode([
                'passed' => count(array_filter($incidents['passed'], function ($incident) {
                    if ($incident['SLA'] === true) {
                        return $incident;
                    }
                })),
                'total' => $incidents['total'],
            ]);
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with getIncidentReportStatus on Ivanti');
        }
        return null;
    }

    public static function getHowToImplementAction($action): ?string
    {
        $howToImplementActionsArr = [
            'getInventoryOfAssets' => 'https://help.ivanti.com/ht/help/en_US/ISM/2018.3/user/Content/ServiceDesk/ConfigurationMgmt/About_Creating_a_Configuration_Item.htm',
            'getOwnershipOfAssets' => 'https://help.ivanti.com/ht/help/en_US/ISM/2018.3/user/Content/ServiceDesk/ConfigurationMgmt/About_Creating_a_Configuration_Item.htm',
            'getChangeManagement' => 'https://help.ivanti.com/ht/help/en_US/ISM/2018.3/user/Content/ServiceDesk/Change/Approving_a_Change_Request.htm#:~:text=All%20change%20request%20approvals%20for,tab%20of%20the%20change%20record.',
            'getResponseToInformationSecurityIncidents' => 'https://help.ivanti.com/ht/help/en_US/ISM/2020/user/Content/ServiceDesk/Incidents/Resolving-Incidents.htm',
        ];

        return array_key_exists($action, $howToImplementActionsArr) ? $howToImplementActionsArr[$action] : null;
    }
}
