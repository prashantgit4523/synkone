<?php

namespace App\Traits\Integration;

use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

trait IvantiTrait
{
    //Naming Comvention fixed for Assets for easy Db insertion
    private function resolve(string $key): string
    {
        return match($key) {
            'name' => 'Name',
            'description' => 'Description',
            'type' => 'CIType',
            'owner' => 'Owner',
            'classification' => 'ivnt_AssetSensitivity',
            'asset_id' => 'RecId',
        };
    }

    //used for
    // 1. getAssets
    // 2. getInventoryOfAssets
    // 3. getOwnershipOfAssets
    private function getAllAssetData($url, $kpi) : ?array
    {
        try {
            $allAssets = $this->getAllData($url);
            return collect($allAssets)->map(function ($asset) use ($kpi) {
                if ($kpi) {
                    return [
                        'name' => $asset[$this->resolve('name')],
                        'asset_id' => $asset[$this->resolve('asset_id')],
                        'owner' => $asset[$this->resolve('owner')],
                        'criticality' => $asset[$this->resolve('classification')],
                        'type' => $this->emptyToNull($asset[$this->resolve('type')]),
                    ];
                } else {
                    return [
                        'name' => $asset[$this->resolve('name')],
                        'description' => $this->emptyToNull($asset[$this->resolve('description')]),
                        'type' => $this->emptyToNull($asset[$this->resolve('type')]),
                        'owner' => $this->emptyToNull($asset[$this->resolve('owner')]),
                        'classification' => $this->emptyToNull($asset[$this->resolve('classification')]),
                        'asset_id' => 'ivanti.' . $asset[$this->resolve('asset_id')],
                        'integration_provider_id' => $this->provider->id,
                    ];
                }
            })->toArray();
        } catch (\Exception$e) {
            return null;
        }
        return null;
    }

    //use for : getChangeManagementFlowStatus
    private function getChangeManagementFlowData($url, $required_values, $additional_values, $kpi): null | array | string
    {
        try {
            $response = $this->getAllData($url);
            if ($response) {
                if ($kpi) {
                    $changeTypes = ['Major', 'Significant'];
                    $changeManagement = collect($response)->whereIn('TypeOfChange', $changeTypes)
                        ->where('CMApprovedBy', '!=', null)->all();
                    $total = count($changeManagement);
                } else {
                    $changeManagement = collect($response)->where('CMApprovedBy', '!=', null)->all();
                }
                $apiResponse = $changeManagement;

                $filter_operator = '=';
                $change = $this->formatResponse(
                    $apiResponse,
                    $required_values,
                    $additional_values,
                    $filter_operator
                );
                return $kpi ? [
                    'passed' => $change,
                    'total' => $total,
                ] : json_encode(array_slice($change, 0, 3));
            }
        } catch (\Exception$th) {
            return null;
        }
        return null;
    }

    //if SLA data is null, It is taken as true and vice versa
    //assumption used is it response time voilation exists then SLA is present
    //Status has to be resolved and category not hardware
    //use for :
    //1. getIncidentReportStatus
    //2. GetLessonsLearnedIncidentReportStatus
    //3. ResponseToInformationSecurityIncidents - kpi
    private function incidentData($url, $required_values, $additional_values, $kpi): null | array | string
    {
        try {
            $response = $this->getAllData($url);
            $total = count($response);
            if ($response) {
                $body = collect($response);
                $incident = $body->where('Status', 'Resolved')
                    ->where('Category', '!=', 'Hardware')->all();
                $apiResponse = $incident;
                $filter_operator = '=';

                $results = $this->formatResponse(
                    $apiResponse,
                    $required_values,
                    $additional_values,
                    $filter_operator
                );
                if (in_array('SLA', $additional_values)) {
                    $results = collect($results)->map(function ($result) {
                        $result['SLA'] = $result['SLA'] ? false : true;
                        return $result;
                    })->toArray();
                }
                return $kpi ? [
                    'passed' => $results,
                    'total' => $total,
                ] : json_encode(array_slice($results, 0, 3));
            }
        } catch (\Throwable | \Exception $e) {
            return null;
        }
        return null;
    }

    //use for getOAuth2StatusConnection
    private function OauthStatus($url, $required_values, $additional_values, $kpi) : null | array | string
    {
        try {
            $response = $this->getAllData($url);
            if ($response) {
                $apiResponse = $response;
                $filter_operator = '=';

                $results = $this->formatResponse(
                    $apiResponse,
                    $required_values,
                    $additional_values,
                    $filter_operator
                );

                return $kpi ? $results : json_encode(array_slice($results, 0, 3));
            }
        } catch (\Exception$th) {
            return null;
        }
        return null;
    }


    //ivanti only returns 100 data per Api call so we need to make multiple calls to get all the data
    private function getAllData($url): ?array
    {
        try {
            $response = $this->client->get($url)->throw();
            if ($response->ok() && $response->status() !== 204) {
                $response = json_decode($response->body(), true);
                $max_skip = intval(floor($response['@odata.count'] / 100)) * 100;
                for ($i = 0; $i <= $max_skip; $i += 100) {
                    $pages[] = $i;
                }
                $responses = Http::pool(fn(Pool $pool) => array_map(fn($page) => $pool->withHeaders(['Authorization' => self::API_PREFIX . $this->fields['api_key']])->get($this->fields['tenant_url'] . '/api/odata/' . $url,
                    [
                        '$top' => 100,
                        '$skip' => $page,
                        '$orderby' => 'CreatedDateTime desc',
                    ]), $pages));
                return collect($responses)->map(function ($response) {
                    return $response['value'];
                })->flatten(1)->toArray();
            }
        } catch (\Exception $th) {
            Log::error('ivanti technical error:'.$th);
            return null;
        }
        return null;
    }

}
