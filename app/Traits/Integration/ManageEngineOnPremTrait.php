<?php

namespace App\Traits\Integration;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

trait ManageEngineOnPremTrait
{
    //return All assets(we take cmdb as assets)
    private function getAllAssets(): ?array
    {
        try {
            $citypes = [
                "Access Point",
                "Business Service",
                "Cluster",
                "Datacenter",
                "Firewall",
                "IP Phone",
                "IPS",
                "Keyboard",
                "NTP",
                "Printer",
                "Projector",
                "Rack",
                "Room Sensor",
                "Router",
                "Scanner",
                "Server",
                "Smart Phone",
                "Storage Device",
                "Switch",
                "Switch Ports",
                "Tablet",
                "UPS",
                "Video Encoder",
                "Laptop",
                "Desktop",
                "Tablet",
                "Others",
                "Workstation"
            ];
            $assets = Arr::flatten(
                array_filter(
                    array_map(
                        function ($ci) {
                            $data = $this->getCiWithAssets($ci);
                            return $this->assetKeyToDataPair($data, $ci);
                        },
                        $citypes
                    )
                ),
                1
            );
            if (count($assets) > 0) {
                return array_map(function ($asset) {
                    $owner = $asset['User'] ?? $asset['Owned By'];
                    return [
                        'name' => $this->emptyToNull($asset['CI Name']),
                        'type' => $this->emptyToNull($asset['CI Type']),
                        'owner' => $this->responseToNull($owner),
                        'classification' => $this->responseToNull($asset['Business Impact']),
                        'description' => $this->responseToNull($asset['Description'] ?? '(null)'),
                        'integration_provider_id' => $this->provider->id,
                        'asset_id' => 'ManageEngineOnPrem.' . $asset['CI Name'] .'-'. $asset['CI Type'],
                    ];
                }, $assets);
            }
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with running getAllAssets on ManageEngine On Prem Trait');
        }
        return null;
    }

    //in manageEngine on prem we need to send post url with OPERATION_NAME,TECHNICIAN_KEY,INPUT_DATA in post
    //sometimes extra headers might give issue
    // logic here: $ci is name of Ci, we get all assets from it xml is the way to get fields
    //there's a counter added as sometimes cmdb send error
    private function getCiWithAssets($ci): ?array
    {
        try {
            foreach (range(0, 2) as $i) {
                $url = $this->url . "/api/cmdb/ci";
                $xml = <<<XML
                            <?xml version="1.0" encoding="UTF-8"?>
                            <API version="1.0" locale="en">
                            <citype>
                            <name>$ci</name>
                            <returnFields>
                            <name>Asset State</name>
                            <name>User</name>
                            <name>CI Name</name>
                            <name>CI Type</name>
                            <name>Business Impact</name>
                            <name>Description</name>
                            <name>Owned By</name>
                            </returnFields>
                            </citype>
                            </API>
                            XML;
                $response = Http::timeout(5)->asForm()->post($url, [
                    'TECHNICIAN_KEY' =>  $this->api_key,
                    'OPERATION_NAME' => 'read',
                    'INPUT_DATA' => $xml
                ]);
                if ($response->ok()) {
                    $body = json_decode($response, true);
                    if ($body['API']['response']['operation']['result']['statuscode'] === 200) {
                        return $body['API']['response']['operation']['Details'];
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with running getCiWithAssets on ManageEngine On Prem Trait');
        }
        return null;
    }

    //return all incidents
    private function getAllIncidents(): ?array
    {
        try {
            $incidentsReq = $this->client->retry(2, 0)->get('v3/requests');
            if ($incidentsReq->ok()) {
                $incidents = json_decode($incidentsReq, true)['requests'];
                $resolvedIncidents = array_filter(array_map(function ($incident) {
                    if ($incident['status']['name'] === 'Resolved') {
                        return $incident['id'];
                    }
                }, $incidents));
                $categoriesList = [
                    'General',
                    'Internet',
                    'Network',
                    'Operating System',
                    'Services',
                    'Software',
                    'User Administration'
                ];

                return array_filter(array_map(function ($incidentId) use ($categoriesList) {
                    $incidentDetail = $this->client->get('v3/requests/' . $incidentId);
                    if ($incidentDetail->ok()) {
                        $incidentBody = json_decode($incidentDetail, true)['request'];
                        if (
                            $incidentBody['category'] &&
                            in_array($incidentBody['category']['name'], $categoriesList)
                            && $incidentBody['request_type']
                            && $incidentBody['request_type']['name'] === 'Incident'
                        ) {
                            return $this->formatIncident($incidentBody);
                        }
                    }
                }, $resolvedIncidents));
            }
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with running getAllIncidents on ManageEngine On Prem Trait');
        }
        return null;
    }

    //return all changes
    private function getAllChanges(): ?array
    {
        try {
            $changeReq = $this->client->get('v3/changes');
            $categoriesList = [
                'General',
                'Internet',
                'Network',
                'Operating System',
                'Services',
                'Software',
                'User Administration'
            ];

            if ($changeReq->ok()) {
                $changes = json_decode($changeReq, true)['changes'];
                return array_filter(array_map(function ($change) use ($categoriesList) {
                    if (
                        $change['category'] && in_array($change['category']['name'], $categoriesList)
                        && $change['approval_status'] && $change['approval_status']['name'] = 'Approved'
                    ) {
                        return [
                            'id' => $this->emptyToNull($change['id'] ?? null),
                            'status' => $this->emptyToNull($change['status']['internal_name'] ?? null),
                            'title' => $this->emptyToNull($change['title'] ?? null),
                            'description' => $this->emptyToNull($change['description'] ?? null),
                            'change_type' => $this->emptyToNull($change['change_type']['name'] ?? null),
                            'created_time' => $this->emptyToNull($change['created_time']['display_value'] ?? null),
                            'impact' => $this->emptyToNull($change['impact']['name'] ?? null),
                            'reason_for_change' => $this->emptyToNull($change['reason_for_change']['name'] ?? null),
                            'approval_status' => $this->emptyToNull($change['approval_status']['name'] ?? null),
                            'risk' => $this->emptyToNull($change['risk']['name'] ?? null),
                            'impact' => $this->emptyToNull($change['impact']['name'] ?? null),
                            'category' => $this->emptyToNull($change['category']['name'] ?? null),
                        ];
                    }
                }, $changes));
            }
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with running getAllChanges on ManageEngine On Prem Trait');
        }
        return null;
    }

    //this formats incident response to other integrations patterns
    private function formatIncident($incident): ?array
    {
        return [
            'id' => $this->emptyToNull($incident['id'] ?? null),
            'status' => $this->emptyToNull($incident['status']['name'] ?? null),
            'resolution' => is_null($incident['resolution']) ? null : strip_tags($incident['resolution']['content']),
            'resolved_time' => $this->emptyToNull($incident['resolved_time']['display_value']) ?? null,
            'sla' => $this->emptyToNull($incident['sla']['name'] ?? null),
            'impact' => $this->emptyToNull($incident['impact']['name'] ?? null),
            'request_type' => $this->emptyToNull($incident['request_type']['name'] ?? null),
            'requester' => $this->emptyToNull($incident['requester']['name'] ?? null),
            'subject' => $this->emptyToNull($incident['subject'] ?? null),
            'description' => is_null($incident['description']) ? null : strip_tags($incident['description']),
            'category' => $this->emptyToNull($incident['category']['name'] ?? null),
            'created_time' => $this->emptyToNull($incident['created_time']['display_value'] ?? null),
            'time_voilation' => $incident['is_overdue'],
        ];
    }

    //in manageEngine,we get list of data and list of fields. this funnction sorts the key and values
    private function assetKeyToDataPair($data, $ci): ?array
    {
        try {
            $keys = Arr::pluck($data['field-names']['name'], 'content');
            $datas = Arr::pluck($data['field-values']['record'], 'value');
            if (Arr::pluck($data, 'totalRecords')[0] === 1) {
                $datas = Arr::flatten($data, 1)[1];
            }
            return array_map(function ($data) use ($keys, $ci) {
                $collect = [];
                for ($i = 0; $i < count($keys); $i++) {
                    $collect = array_merge($collect, [
                        $keys[$i] => $data[$i] ?? null,
                    ], [
                        'CI Type' => $ci,
                    ]);
                }
                return $collect;
            }, $datas);
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with running assetKeyToDataPair on ManageEngine On Prem Trait');
        }
        return null;
    }

    //in manageEngine on prem CMDB if a value is null it returns (null) as string..this is to format the null response
    private function responseToNull($value)
    {
        return $value === '(null)' ? null : $value;
    }
}
