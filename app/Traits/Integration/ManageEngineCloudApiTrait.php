<?php

namespace App\Traits\Integration;

trait ManageEngineCloudApiTrait
{
    //return All assets(we take cmdb as assets)
    private function getAllAssets() : ?array
    {
        try {
            $assetsRes = $this->client->get('/cmdb');
            if ($assetsRes->ok()) {
                $assets = json_decode($assetsRes, true)['cmdb'];
                $except = ['People','Support Group','Team'];
                return array_filter(array_map(function ($asset) use($except){
                    if($asset['ci_type']['display_name_plural'] && !in_array($asset['ci_type']['display_name_plural'],$except)){
                        return [
                            'name' => $this->emptyToNull($asset['name']),
                            'type' => $this->emptyToNull($asset['ci_type']['display_name_plural']),
                            'owner' => $this->emptyToNull($asset['ci_attributes']['ref_owned_by']['name'] ?? null),
                            'classification' => $this->emptyToNull($asset['ci_attributes']['ref_business_impact']['name'] ?? null),
                            'description' => $this->emptyToNull($asset['description'] ?? null),
                            'integration_provider_id' => $this->provider->id,
                            'asset_id' => 'ManageEngineCloud.' . $asset['id'],
                        ];
                    }
                }, $assets));
            }
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with running getAllAssets on ManageEngine Cloud Trait');
        }
        return null;
    }
    //return all incidents
    private function getAllIncidents() : ?array
    {
        try {
            $incidentsReq = $this->client->retry(2, 0)->get('/requests');
            if ($incidentsReq->ok()) {
                $incidents = json_decode($incidentsReq, true)['requests'];
                $resolvedIncidents = array_filter(array_map(function ($incident) {
                    if ($incident['status']['internal_name'] === 'Resolved') {
                        return $incident['id'];
                    }
                }, $incidents));
                $categoriesList = ['General', 'Internet', 'Network', 'Operating System', 'Services', 'Software', 'User Administration'];
                return array_filter(array_map(function ($incident_id) use ($categoriesList) {
                    $incidentDetail = $this->client->get('requests/' . $incident_id);
                    if ($incidentDetail->ok()) {
                        $incidentBody = json_decode($incidentDetail, true)['request'];
                        if ($incidentBody['category'] &&
                            in_array($incidentBody['category']['name'], $categoriesList) && $incidentBody['request_type'] &&
                            $incidentBody['request_type']['name'] === 'Incident') {
                            return $this->formatIncident($incidentBody);
                        }
                    }
                }, $resolvedIncidents));
            }
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with running getAllIncidents on ManageEngine Cloud Trait');
        }
        return null;
    }

    //return all changes
    private function getAllChanges(): ?array
    {
        try {
            $changeReq = $this->client->get('/changes');
            $categoriesList = ['General', 'Internet', 'Network', 'Operating System', 'Services', 'Software', 'User Administration'];

            if ($changeReq->ok()) {
                $changes = json_decode($changeReq, true)['changes'];
                return array_filter(array_map(function ($change) use ($categoriesList) {
                    if ($change['category'] && in_array($change['category']['name'], $categoriesList)
                        && $change['approval_status'] && $change['approval_status']['name'] = 'Approved') {
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
            $this->logException($e, 'Something went wrong with running getAllChanges on ManageEngine Cloud Trait');
        }
        return null;
    }

    private function formatIncident($incident): ?array
    {
        return [
            'id' => $this->emptyToNull($incident['id'] ?? null),
            'status' => $this->emptyToNull($incident['status']['internal_name'] ?? null),
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
}
