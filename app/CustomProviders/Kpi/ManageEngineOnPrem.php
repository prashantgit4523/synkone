<?php

namespace App\CustomProviders\Kpi;

use App\CustomProviders\CustomProvider;
use App\CustomProviders\Interfaces\ICustomAuth;
use App\CustomProviders\Interfaces\IHaveHowToImplement;
use App\Traits\Integration\IntegrationApiTrait;
use App\Traits\Integration\ManageEngineOnPremTrait;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class ManageEngineOnPrem extends CustomProvider implements ICustomAuth, IHaveHowToImplement
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
            if ($this->assets) {
                $passed = count(array_filter($this->assets, function ($asset) {
                    return $asset['classification'] !== null;
                }));
                $total = count($this->assets);
                return json_encode([
                    'passed' => $passed,
                    'total' => $total,
                ]);
            }
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with running getInventoryOfAssets on ManageEngine On Prem');
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
            if ($this->assets) {
                $passed = count(array_filter($this->assets, function ($asset) {
                    return $asset['owner'] !== null;
                }));
                $total = count($this->assets);
                return json_encode([
                    'passed' => $passed,
                    'total' => $total,
                ]);
            }
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with running getOwnershipOfAssets on ManageEngine On Prem');
        }
        return null;
    }

    // KPI: Number of major changes approved by information security.
    // Number of resolved changes.
    // Number of rejected changes.
    // on hold as per Amar(we need to make some decision on how to implement this in real world situation)
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
            if ($this->incidents) {
                $passed = count(array_filter($this->incidents, function ($incident) {
                    return $incident['time_voilation'] !== true;
                }));
                $total = count($this->incidents);
                return json_encode([
                    'passed' => $passed,
                    'total' => $total,
                ]);
            }
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with running getIncidentReportStatus on ManageEngine On Prem');
        }
        return null;
    }

    public static function getHowToImplementAction($action): ?string
    {
        $baseUrl = 'https://www.manageengine.com/products/service-desk-msp/help/adminguide/';
        $assetUrl = $baseUrl . 'assets/assets/it_assets/adding_it_assets.html';
        $howToImplementActionsArr = [
            'getInventoryOfAssets' => $assetUrl,
            'getOwnershipOfAssets' => $assetUrl,
            'getChangeManagement' => $baseUrl . 'change/creating_new_change.html',
            'getResponseToInformationSecurityIncidents' => $baseUrl . 'requests/close-request.html',
        ];

        return array_key_exists($action, $howToImplementActionsArr) ? $howToImplementActionsArr[$action] : null;
    }
}
