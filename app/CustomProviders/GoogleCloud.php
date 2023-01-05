<?php

namespace App\CustomProviders;

use App\CustomProviders\Interfaces\ICustomAuth;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use Illuminate\Support\Facades\Http;
use App\Traits\Integration\IntegrationApiTrait;
use Google\Auth\Middleware\AuthTokenMiddleware;
use App\CustomProviders\Interfaces\IInfrastructure;
use Google\Auth\Credentials\ServiceAccountCredentials;
use App\CustomProviders\Interfaces\IHaveHowToImplement;

class GoogleCloud extends CustomProvider implements ICustomAuth, IInfrastructure, IHaveHowToImplement
{
    use IntegrationApiTrait;
    private $projects = [];

    public function __construct()
    {
        parent::__construct('google-cloud', false);
        if($this->fields['private_key']){
            $this->getAndSetProjects();
        }
    }

    public function attempt(array $fields): bool
    {
        try {
            $this->callApi("https://cloudresourcemanager.googleapis.com/v1/projects", $fields);
            $this->connect($this->provider, $fields);

            return true;
        } catch (\Exception $e) {
           writeLog('error', 'GoogleCloud attempt connect failed: '.$e->getMessage());
        }

        return false;
    }

    public function getWafStatus(): ?string
    {
        try {
            if (count($this->projects)) {
                $data_to_return = [];
                foreach ($this->projects as $project) {
                    $url = url("https://compute.googleapis.com/compute/v1/projects/{$project['projectId']}/global/securityPolicies");

                    $response = $this->callApi($url);

                    if ($response->ok()) {
                        $cloudarmors = json_decode($response->body(), true);

                        if (isset($cloudarmors['items']) && count($cloudarmors['items'])) {
                            $cloudarmor_files = [];
                            foreach ($cloudarmors['items'] as $item) {
                                $policy = [];
                                $policy['id'] = $item['id'];
                                $policy['name'] = $item['name'];
                                $policy['description'] = $item['description'];
                                $policy['totalRules'] = count($item['rules']);
                                array_push($cloudarmor_files, $policy);
                            }
                            if (count($cloudarmor_files)) {
                                $project_implemented = [];
                                $project_implemented['project_id'] = $project['projectId'];
                                $project_implemented['items'] = $cloudarmor_files;
                                array_push($data_to_return, $project_implemented);
                            }
                        }
                    }
                }
                if (count($data_to_return))
                    return json_encode($data_to_return);
            }
        } catch (\Exception $e) {
            writeLog('error', 'GoogleCloud getWafStatus implementation failed: '.$e->getMessage());
        }
        return null;
    }

    public function getMfaStatus(): ?string
    {
        return null;
    }

    public function getKeyvaultStatus(): ?string
    {
        try {
            if (count($this->projects) > 0) {
                $data_to_return = [];
                foreach ($this->projects as $project) {
                    $locations = $this->getAndSetLocations($project);
                    if (!empty($locations)) {
                        foreach ($locations as $location) {
                            $url = url("https://cloudkms.googleapis.com/v1/projects/{$project['projectId']}/locations/{$location['locationId']}/keyRings");
                            
                            $response = $this->callApi($url);

                            $keyRings = json_decode($response->body(), true);

                            if ($response->ok() && array_key_exists('totalSize', $keyRings)) {
                                $project_implemented = [];
                                $project_implemented['project_id'] = $project['projectId'];
                                $project_implemented['location'] = $location['locationId'];
                                $project_implemented['totalKeySize'] = $keyRings['totalSize'];
                                array_push($data_to_return, $project_implemented);
                            }
                            if (!empty($keyRings)) {
                                break;
                            }
                        }
                    }
                }
                if (count($data_to_return)) {
                    return json_encode($data_to_return);
                }
            }
        } catch (\Exception $e) {
            writeLog('error', 'GoogleCloud getKeyvaultStatus implementation failed: '.$e->getMessage());
        }

        return null;
    }

    public function getLoggingStatus(): ?string
    {
        // same api and implementation to cpu monitor status
        return $this->getCpuMonitorStatus();
    }

    public function getCpuMonitorStatus(): ?string
    {
        try {
            if (count($this->projects)) {
                $data_to_return = [];
                foreach ($this->projects as $project) {
                    $url = url("https://monitoring.googleapis.com/v3/projects/{$project['projectId']}/alertPolicies");
                    
                    $response = $this->callApi($url);

                    if ($response->ok()) {
                        $alert_policies = json_decode($response->body(), true);

                        if (isset($alert_policies['alertPolicies']) && count($alert_policies)) {
                            $project_alert_policies = [];
                            foreach ($alert_policies['alertPolicies'] as $item) {
                                if ($item['enabled'] === true) {
                                    $alert = [];
                                    $alert['displayName'] = $item['displayName'];
                                    $alert['name'] = $item['name'];
                                    $alert['alertStrategy'] = $item['alertStrategy'];
                                    $alert['enabled'] = $item['enabled'];
                                    array_push($project_alert_policies, $alert);
                                }
                            }
                            if (count($project_alert_policies)) {
                                $project_implemented = [];
                                $project_implemented['project_id'] = $project['projectId'];
                                $project_implemented['items'] = $project_alert_policies;
                                array_push($data_to_return, $project_implemented);
                            }
                        }
                    }
                }
                if (count($data_to_return))
                    return json_encode($data_to_return);
            }
        } catch (\Exception $e) {
            writeLog('error', 'GoogleCloud getCpuMonitorStatus implementation failed: '.$e->getMessage());
        }
        return null;
    }

    public function getBackupsStatus(): ?string
    {
        try {
            if (count($this->projects)) {
                $data_to_return = [];
                foreach ($this->projects as $project) {
                    $url = url("https://compute.googleapis.com/compute/v1/projects/{$project['projectId']}/global/snapshots");
                    
                    $response = $this->callApi($url);

                    if ($response->ok()) {
                        $snapshots = json_decode($response->body(), true);

                        if (isset($snapshots['items']) && count($snapshots['items'])) {
                            $snapshot_files = [];
                            foreach ($snapshots['items'] as $item) {
                                $snap = [];
                                $snap['id'] = $item['id'];
                                $snap['name'] = $item['name'];
                                $snap['sourceDiskId'] = $item['sourceDiskId'];
                                $snap['storageLocations'] = $item['storageLocations'];
                                array_push($snapshot_files, $snap);
                            }
                            if (count($snapshot_files)) {
                                $project_implemented = [];
                                $project_implemented['project_id'] = $project['projectId'];
                                $project_implemented['items'] = $snapshot_files;
                                array_push($data_to_return, $project_implemented);
                            }
                        }
                    }
                }
                if (count($data_to_return))
                    return json_encode($data_to_return);
            }
        } catch (\Exception $e) {
            writeLog('error', 'GoogleCloud getBackupsStatus implementation failed: '.$e->getMessage());
        }

        return null;
    }

    public function getNetworkSegregationStatus(): ?string
    {
        try {
            if (count($this->projects)) {
                $data_to_return = [];
                foreach ($this->projects as $project) {
                    $url = url("https://compute.googleapis.com/compute/v1/projects/{$project['projectId']}/global/firewalls");
                    
                    $response = $this->callApi($url);

                    if ($response->ok()) {
                        $firewalls = json_decode($response->body(), true);

                        if (isset($firewalls['items']) && count($firewalls['items'])) {
                            $denied_rules = [];
                            foreach ($firewalls['items'] as $item) {
                                if (array_key_exists('denied', $item)) {
                                    $denied = [];
                                    $denied['id'] = $item['id'];
                                    $denied['name'] = $item['name'];
                                    $denied['description'] = $item['description'];
                                    $denied['denied'] = $item['denied'];
                                    array_push($denied_rules, $denied);
                                }
                            }
                            if (count($denied_rules)) {
                                $project_implemented = [];
                                $project_implemented['project_id'] = $project['projectId'];
                                $project_implemented['items'] = $denied_rules;
                                array_push($data_to_return, $project_implemented);
                            }
                        }
                    }
                }

                if (count($data_to_return))
                    return json_encode($data_to_return);
            }
        } catch (\Exception $e) {
            writeLog('error', 'GoogleCloud getNetworkSegregationStatus implementation failed: '.$e->getMessage());
        }

        return null;
    }

    public function getClassificationStatus(): ?string
    {
        return null;
    }

    public function getInactiveUsersStatus(): ?string
    {
        return null;
    }

    public function getSecureDataWipingStatus(): ?string
    {
        return json_encode(["message" => "Google cloud is deleting all data securely."]);
    }

    public function getAdminMfaStatus(): ?string
    {
        return null;
    }

    public function getHddEncryptionStatus(): ?string
    {
        try {
            if (count($this->projects)) {
                $data_to_return = [];
                foreach ($this->projects as $project) {
                    $url = url("https://compute.googleapis.com/compute/v1/projects/{$project['projectId']}/aggregated/disks");
                    
                    $response = $this->callApi($url);

                    if ($response->ok()) {
                        $body = json_decode($response->body(), true);
                        if (isset($body['items']) && count($body['items'])) {
                            $project_hdd_encryption_status = [];
                            foreach ($body['items'] as $item) {
                                if (!empty($item['disks'])) {
                                    foreach ($item['disks'] as $diskData) {
                                        $disk = [];
                                        $disk['id'] = $diskData['id'];
                                        $disk['name'] = $diskData['name'];
                                        $disk['kind'] = $diskData['kind'];
                                        $disk['sizeGb'] = $diskData['sizeGb'];
                                        $disk['zone'] = $diskData['zone'];
                                        $disk['type'] = $diskData['type'];
                                        $disk['physicalBlockSizeBytes'] = $diskData['physicalBlockSizeBytes'];
                                        $disk['status'] = $diskData['status'];
                                        array_push($project_hdd_encryption_status, $disk);
                                    }
                                }
                            }

                            if (count($project_hdd_encryption_status)) {
                                $project_implemented = [];
                                $project_implemented['project_id'] = $project['projectId'];
                                $project_implemented['items'] = $project_hdd_encryption_status;
                                array_push($data_to_return, $project_implemented);
                            }
                        }
                    }
                }

                if (count($data_to_return))
                    return json_encode($data_to_return);
            }
        } catch (\Exception $e) {
            writeLog('error', 'GoogleCloud getHddEncryptionStatus implementation failed: '.$e->getMessage());
        }

        try {
            if (count($this->projects)) {
                $data_to_return = [];
                foreach ($this->projects as $project) {
                    $url = url("https://storage.googleapis.com/storage/v1/b?project={$project['projectId']}");

                    $response = $this->callApi($url);

                    if ($response->ok()) {
                        $body = json_decode($response->body(), true);

                        if (isset($body['items']) && count($body['items'])) {

                            $project_hdd_encryption_status = [];
                            foreach ($body['items'] as $item) {
                                $disk = [];
                                $disk['id'] = $item['id'];
                                $disk['name'] = $item['name'];
                                $disk['kind'] = $item['kind'];
                                $disk['storageClass'] = $item['storageClass'];
                                $disk['location'] = $item['location'];
                                $disk['locationType'] = $item['locationType'];
                                $disk['satisfiesPZS'] = $item['satisfiesPZS'] ?? '';
                                array_push($project_hdd_encryption_status, $disk);
                            }

                            if (count($project_hdd_encryption_status)) {
                                $project_implemented = [];
                                $project_implemented['project_id'] = $project['projectId'];
                                $project_implemented['items'] = $project_hdd_encryption_status;
                                array_push($data_to_return, $project_implemented);
                            }
                        }
                    }
                }
                if (count($data_to_return))
                    return json_encode($data_to_return);
            }
        } catch (\Exception $e) {
            writeLog('error', 'GoogleCloud getHddEncryptionStatus implementation failed: '.$e->getMessage());
        }

        return null;
    }

    public function getIDSStatus(): ?string
    {
        try {
            if (count($this->projects)) {
                foreach ($this->projects as $project) {
                    $url = url("https://serviceusage.googleapis.com/v1/projects/{$project['projectId']}/services/ids.googleapis.com");

                    $response = $this->callApi($url);

                    if ($response->ok()) {
                        $body = json_decode($response->body(), true);
                        $apiResponse = $body['value'] ?? $body;
                        if (isset($apiResponse['state']) && $apiResponse['state'] == "ENABLED") {
                            $required_values = ["state" => "ENABLED"];
                            $additional_values = ['name', 'state'];
                            $filter_operator = '=';

                            return json_encode($this->formatResponse(
                                $apiResponse,
                                $required_values,
                                $additional_values,
                                $filter_operator
                            ));
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            writeLog('error', 'GoogleCloud getIDSStatus implementation failed: '.$e->getMessage());
        }
        return null;
    }

    public function getAndSetLocations($project)
    {
        $url = url("https://cloudkms.googleapis.com/v1/projects/{$project['projectId']}/locations");

        $locationResponse = $this->callApi($url);

        if ($locationResponse->ok()) {
            $locationData = json_decode($locationResponse->body(), true);
            if (count($locationData['locations']) > 0) {
                return $locationData['locations'];
            }
        }
    }

    public function getAndSetProjects()
    {
        try {
            $response = $this->callApi('https://cloudresourcemanager.googleapis.com/v1/projects');

            if ($response && $response->ok()) {
                $projects = json_decode($response->body(), true);

                if (count($projects['projects'])) {
                    $this->projects = $projects['projects'];
                }
            }
        } catch (\Exception $e) {
            writeLog('error', 'GoogleCloud getAndSetProjects has an issue: '.$e->getMessage());
        }
    }

    public function callApi($url, $fields = null)
    {
        $fields = json_decode($fields['private_key'] ?? $this->fields['private_key'], true);
            
        $sa = new ServiceAccountCredentials(config('services.google-cloud.scopes'), $fields);

        $middleware = new AuthTokenMiddleware($sa);
        $stack = HandlerStack::create();
        $stack->push($middleware);

        return Http::withOptions([
            'handler' => $stack,
            'base_uri' => null,
            'auth' => 'google_auth' // authorize all requests
        ])->get($url);
    }

    public static function getHowToImplementAction($action): ?string
    {
        $howToImplementActionsArr = [
            "getWafStatus" => "https://cloud.google.com/armor/docs/configure-security-policies",
            "getKeyvaultStatus" => "https://cloud.google.com/security-key-management",
            "getLoggingStatus" => "https://cloud.google.com/logging/docs/audit",
            "getCpuMonitorStatus" => "https://cloud.google.com/logging/docs/audit",
            "getBackupsStatus" => "https://cloud.google.com/compute/docs/disks/create-snapshots",
            "getNetworkSegregationStatus" => "https://cloud.google.com/vpc/docs/create-modify-vpc-networks ",
            "getHddEncryptionStatus" => "https://cloud.google.com/compute/docs/disks/create-disk-from-source",
            "getIDSStatus" => "https://cloud.google.com/intrusion-detection-system"
        ];

        return array_key_exists($action, $howToImplementActionsArr) ? $howToImplementActionsArr[$action] : null;
    }
}
