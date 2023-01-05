<?php

namespace App\CustomProviders;

use App\CustomProviders\Interfaces\IHaveHowToImplement;
use Illuminate\Support\Facades\Http;
use App\Traits\Integration\IntegrationApiTrait;
use App\CustomProviders\Interfaces\IInfrastructure;

class DigitalOcean extends CustomProvider implements IInfrastructure, IHaveHowToImplement
{
    use IntegrationApiTrait;

    public function __construct()
    {
        parent::__construct('digitalocean');
    }


    public function getWafStatus(): ?string
    {
        try {
            // same as firewall
            return $this->getNetworkSegregationStatus();
        } catch (\Exception $e) {
            writeLog('error', 'DigitalOcean getWafStatus implementation failed: ' . $e->getMessage());
        }
    }

    public function getMfaStatus(): ?string
    {
        return null;
    }
    /**
     * getKeyVaultStatus
     * @return string|null
     */
    public function getKeyvaultStatus(): ?string
    {
        try {
            return json_encode(["message" => "Digital Ocean is SSL certified."]);
        } catch (\Exception $e) {
            writeLog('error', 'DigitalOcean getKeyvaultStatus implementation failed: ' . $e->getMessage());
        }
    }

    public function getLoggingStatus(): ?string
    {
        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get('https://api.digitalocean.com/v2/actions?page=1&per_page=20');

            if ($response->ok()) {
                $body = json_decode($response->body(), true);
                $apiResponse = $body["actions"];
                if (empty($apiResponse)) {
                    return null;
                } else {
                    $additional_values = ['id', 'type', 'status', 'started_at', 'completed_at', 'resource_type'];
                    return json_encode($this->formatResponse(
                        $apiResponse,
                        [],
                        $additional_values,
                        null
                    ));
                }
            }
        } catch (\Exception $e) {
            writeLog('error', 'DigitalOcean getLoggingStatus implementation failed: ' . $e->getMessage());
        }
        return null;
    }

    public function getCpuMonitorStatus(): ?string
    {
        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get('https://api.digitalocean.com/v2/monitoring/alerts');

            if ($response->ok()) {
                $body = json_decode($response->body(), true);
                $apiResponse = $body["policies"];
                if (empty($apiResponse)) {
                    return null;
                } else {
                    $additional_values = ['uuid', 'type', 'description', 'alerts', 'enabled'];
                    return json_encode($this->formatResponse(
                        $apiResponse,
                        [],
                        $additional_values,
                        null
                    ));
                }
            }
        } catch (\Exception $e) {
            writeLog('error', 'DigitalOcean getCpuMonitorStatus implementation failed: ' . $e->getMessage());
        }
        return null;
    }

    public function getBackupsStatus(): ?string
    {
        try {
            $availableDroplets = Http::withToken($this->provider->accessToken)
                ->get('https://api.digitalocean.com/v2/droplets');

            if ($availableDroplets->ok()) {
                $body = json_decode($availableDroplets->body(), true);
                $apiResponse = $body;
                if (!empty($apiResponse["droplets"]) && count($apiResponse["droplets"])) {
                    $dropletResponse = [];
                    $droplets = $apiResponse["droplets"];
                    foreach ($droplets as $eachDroplet) {
                        if (!empty($eachDroplet["next_backup_window"]) && $eachDroplet["next_backup_window"] != null) {
                            $dropletResponse[] = ["next_backup_window" => $eachDroplet["next_backup_window"], "name" => $eachDroplet["name"], "id" => $eachDroplet["id"]];
                        }
                    }
                    if (count($dropletResponse) > 0) {
                        return json_encode($dropletResponse);
                    } else {
                        return null;
                    }
                }
            }
        } catch (\Exception $e) {
            writeLog('error', 'DigitalOcean getBackupsStatus implementation failed: ' . $e->getMessage());
        }
        return null;
    }

    public function getNetworkSegregationStatus(): ?string
    {
        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get('https://api.digitalocean.com/v2/firewalls');

            if ($response->ok()) {
                $body = json_decode($response->body(), true);
                $apiResponse = $body['firewalls'];
                if (empty($apiResponse)) {
                    return null;
                } else {
                    $additional_values = ['id', 'name', 'inbound_rules'];
                    return json_encode($this->formatResponse(
                        $apiResponse,
                        [],
                        $additional_values,
                        null
                    ));
                }
            }
        } catch (\Exception $e) {
            writeLog('error', 'DigitalOcean getNetworkSegregationStatus implementation failed: ' . $e->getMessage());
        }
        return null;
    }
    /**
     * getClassificationStatus
     * @return string|null
     */
    public function getClassificationStatus(): ?string
    {
        return null;
    }
    /**
     * getInactiveUsersStatus
     * !! Decided not to do this check.
     * @return string|null
     */
    public function getInactiveUsersStatus(): ?string
    {
        return null;
    }
    /**
     * getSecureDataWipingStatus
     * @return string|null
     */
    public function getSecureDataWipingStatus(): ?string
    {
        try {
            return json_encode(["message" => "Digital Ocean is deleting all data securely."]);
        } catch (\Exception $e) {
            writeLog('error', 'DigitalOcean getSecureDataWipingStatus implementation failed: ' . $e->getMessage());
        }
        return null;
    }
    /**
     * !! Decided not to do this check.
     * @return string
     */
    public function getAdminMfaStatus(): ?string
    {
        return null;
    }

    public function getHddEncryptionStatus(): ?string
    {
        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get('https://api.digitalocean.com/v2/databases');

            if ($response->ok()) {
                $body = json_decode($response->body(), true);
                $apiResponse = $body['databases'];
                if (empty($apiResponse)) {
                    return null;
                } else {
                    $additional_values = ['id', 'name', 'engine', 'version', 'size'];
                    $required_values = ["status" => 'online'];
                    $filter_operator = '=';
                    return json_encode($this->formatResponse(
                        $apiResponse,
                        $required_values,
                        $additional_values,
                        $filter_operator
                    ));
                }
            }
        } catch (\Exception $e) {
            writeLog('error', 'DigitalOcean getHddEncryptionStatus implementation failed: ' . $e->getMessage());
        }
        return null;
    }

    public static function getHowToImplementAction($action): ?string
    {
        $howToImplementActionsArr = [
            'getCpuMonitorStatus' => 'https://docs.digitalocean.com/reference/api/api-reference/#operation/monitoring_list_alertPolicy',
            'getBackupsStatus' => 'https://docs.digitalocean.com/reference/api/api-reference/#operation/droplets_list',
            'getNetworkSegregationStatus' => 'https://docs.digitalocean.com/reference/api/api-reference/#operation/firewalls_list',
            'getClassificationStatus' => '',
            'getInactiveUsersStatus' => '',
            'getSecureDataWipingStatus' => '',
            'getAdminMfaStatus' => '',
            'getHddEncryptionStatus' => 'https://docs.digitalocean.com/reference/api/api-reference/#operation/databases_list_clusters',
        ];
        return array_key_exists($action, $howToImplementActionsArr) ? $howToImplementActionsArr[$action] : null;
    }
}
