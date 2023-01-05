<?php

namespace App\CustomProviders;

use App\CustomProviders\Interfaces\IDeviceManagement;
use App\CustomProviders\Interfaces\IHaveHowToImplement;
use App\Traits\Integration\IntegrationApiTrait;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use App\Traits\Integration\KandjiApiTrait;

class Kandji extends CustomProvider implements IDeviceManagement, IHaveHowToImplement
{

    use IntegrationApiTrait;
    use KandjiApiTrait;
    private PendingRequest $client;

    private const DEVICES = '/devices/';
    private const ALL_DEVICES = '/devices';
    private const BLUEPRINT_URL = 'https://support.kandji.io/support/solutions/articles/72000559777-creating-a-blueprint';


    // Get Token by creating new API Token in 'https://yourdomain.kandji.io/my-company/access'
    // https://support.kandji.io/support/solutions/articles/72000560503-foqal-integration
    public function __construct()
    {
        parent::__construct('kandji');
        $this->api_key = $this->fields['api_key'];
        $this->url = $this->fields['url'];

        $this->client = Http::withHeaders([
            'Authorization' => 'Bearer '.$this->api_key,   // for demo, env('KANDJI_TOKEN')=815ed86d-4d97-4f99-8960-69cb84b8cadc
        ])
        ->timeout(10)
        ->baseUrl($this->url);
    }

    public function attempt(array $fields): bool
    {
        if (
            Http::timeout(5)
            ->withHeaders(['Authorization' => 'Bearer '.$fields['api_key']])
            ->baseUrl($fields['url'])
            ->get(self::ALL_DEVICES)
            ->failed()) {
            return false;
        }

        $this->connect($this->provider, $fields);
        return true;
    }

    // check for usb blocked devices
    // return null because
    // Kandji doesn't provide API to get the status of the external storage device.
    // Also we're informed that control to disable USB Storage has been deprecated by Apple so Kandji can't control USB Storage
    // https://developer.apple.com/documentation/devicemanagement/mediamanagementallowedmedia
    // Standard mapping is "A.8.3.1" standard ISO 27001-2-2013
    public function getBlockedUsbStatus(): ?string
    {
        return null;
    }

    // Check if users of device use complex password
    // Kandji use 'passcode' library to enforce device users the complex password
    // so check if 'passcode' library is implemented in devices or not
    // Standard mapping is "A.9.3.1" standard ISO 27001-2-2013
    public function getPasswordComplexityStatus(): ?string
    {
        try {
            $devices = $this->getDevices();

            if (!empty($devices)) {
                foreach ($devices as $device) {
                    if($device['mdm_enabled'] === false)
                    {
                        $data_to_return = [];
                        $passcode_status = $this->getLibraryItems($device['device_id']);

                        if (!empty($passcode_status) && count($passcode_status)) {
                            $kandji_devices = [];
                            $kandji_devices['device_id'] = $device['device_id'];
                            $kandji_devices['device_name'] = $device['device_name'];
                            $kandji_devices['passcode'] = $passcode_status;
                            array_push($data_to_return, $kandji_devices);
                        }
                    }
                }

                if (count($data_to_return)) {
                    return json_encode($data_to_return);
                }
            }
        } catch (\Exception $e) {
            writeLog('error', 'Kanji getPasswordComplexityStatus implementation failed: ' . $e->getMessage());
            return null;
        }

        return null;
    }

    // Check for conditional access with root permission
    // Kandji doesn't provide API for this, so return null
    // Standard mapping is "A.6.2.2" standard ISO 27001-2-2013
    public function getConditionalAccessStatus(): ?string
    {
        return $this->getBlockedUsbStatus();
    }

    // List the devices, who doesn't have removed the policy
    // Mapping in standard mappings  is "A.6.2.1"  standard ISO 27001-2-2013
    public function getMobileDeviceStatus(): ?string
    {
        try {
            $response = $this->client->get(self::ALL_DEVICES);

            if ($response->ok()) {
                $body = json_decode($response->body(), true);
                $data_to_return = [];
                foreach ($body as $item) {
                    if($item['mdm_enabled'] === false)
                    {
                        $data = [];
                        $data['device_name'] = $item['device_name'];
                        $data['is_removed'] = $item['is_removed'];
                        $data['model'] = $item['model'];
                        $data['platform'] = $item['platform'];
                        $data['os_version'] = $item['os_version'];
                        $data['last_check_in'] = $item['last_check_in'];
                        array_push($data_to_return, $data);
                    }
                }

                if (count($data_to_return)) {
                    return json_encode($data_to_return);
                }
            }
        } catch (\Exception $e) {
            writeLog('error', 'Kanji getMobileDeviceStatus implementation failed: ' . $e->getMessage());
            return null;
        }
    }

    // Kandji provides device's volume details in Device Details API
    // volume details will proide the encrypted status of the volume/disk in device
    // control will be implemented if implementationPercentage > 50
    // Standard mapping is "A.10.1.1" standard ISO 27001-2-2013
    public function getHddEncryptionStatus(): ?string
    {
        try {
            $devices = $this->getDevices();

            if (!empty($devices)) {
                $total = 0;
                $passed = 0;
                $data_to_return = [];

                foreach ($devices as $device) {;
                    $hddResponse = $this->hddEncryptedDevices($device);
                    if(!empty($hddResponse))
                    {
                        $total++;
                        if($hddResponse['passed'] != 0)
                        {
                            $passed++;
                            array_push($data_to_return, $hddResponse['kandji_devices']);
                        }
                    }
                }

                $implementationPercentage = ($passed / $total) * 100;
                if($total > 0 && $implementationPercentage > 50)
                {
                    return json_encode($data_to_return);
                }
            }
        } catch (\Exception $e) {
            writeLog('error', 'Kanji getHddEncryptionStatus implementation failed: ' . $e->getMessage());
            return null;
        }

        return null;
    }

    // check for the device that has removed or missing policy
    // policy removed means, device is inactive
    // control will be implemented if implementationPercentage > 50
    // Standard mapping is "A.11.2.8" standard ISO 27001-2-2013
    public function getInactivityStatus(): ?string
    {
        try {
            $response = $this->client->get(self::ALL_DEVICES);

            if ($response->ok()) {
                $body = json_decode($response->body(), true);
                $total = 0;
                $passed = 0;

                $data_to_return = [];
                foreach ($body as $item) {
                    $total++;

                    if($item['mdm_enabled'] === false)
                    {
                        $passed++;

                        $data = [];
                        $data['device_name'] = $item['device_name'];
                        $data['model'] = $item['model'];
                        $data['platform'] = $item['platform'];
                        $data['os_version'] = $item['os_version'];
                        $data['last_check_in'] = $item['last_check_in'];
                        array_push($data_to_return, $data);
                    }
                }

                if($total > 0)
                {
                    $implementationPercentage = ($passed / $total) * 100;
                    if ($implementationPercentage > 50) {
                        return json_encode($data_to_return);
                    }
                }
            }
        } catch (\Exception $e) {
            writeLog('error', 'Kanji getInactivityStatus implementation failed: ' . $e->getMessage());
            return null;
        }

        return null;
    }

    // check if antivirus is installed on devices or not
    // Kandji doesn't provide specific API for the status of antivirus.
    // so we've listed possible antivirus lists for Mac, iphone, ipads
    // then we will check if any of the listed antivirus is installed on devices or not
    // from 'Get Device Apps' API ok Kandji
    // https://api.kandji.io/#f8cd9733-89b6-40f0-a7ca-76829c6974df
    // Note: Kandji API will list the software or app installed on device only after 24hours of installment
    // Ref: https://support.kandji.io/support/solutions/articles/72000559825-macos-check-in
    // control will be implemented if implementationPercentage > 50
    // Standard mapping is "A.12.2.1" standard ISO 27001-2-2013
    public function getAntivirusStatus(): ?string
    {
        try {
            $devicesResponse = $this->client->get(self::ALL_DEVICES);

            if ($devicesResponse->ok()) {
                $devices = json_decode($devicesResponse->body(), true);
                $total = 0;
                $passed = 0;
                $data_to_return = [];

                foreach ($devices as $device) {
                    $appResponse = $this->antivirusApps($device);
                    if(!empty($appResponse))
                    {
                        $total++;
                        if(count($appResponse['kandji_devices']))
                        {
                            $passed++;
                            array_push($data_to_return, $appResponse['kandji_devices']);
                        }
                    }
                }

                $implementationPercentage = ($passed / $total) * 100;
                if($total > 0 && $implementationPercentage > 50)
                {
                    return json_encode($data_to_return);
                }
            }
        } catch (\Exception $e) {
            writeLog('error', 'Kanji getAntivirusStatus implementation failed: ' . $e->getMessage());
            return null;
        }

        return null;
    }

    // check if date and time of device is set automatically update or not
    // for each devices, we request for their parameters from 'Get Device Parameters' API of Kandji
    // then we search for parameter with item_id = d011e3a4-eebe-4af2-adf8-aa41c11efacf which means
    // 'Ensure date and time is set automatically', check for its status=PASS
    // https://github.com/kandji-inc/support/wiki/Devices-API---Parameter-Correlations
    // control will be implemented if implementationPercentage > 50
    // Standard mapping is "A.12.4.4" standard ISO 27001-2-2013
    public function getNtpStatus(): ?string
    {
        return $this->getParameterStatus('d011e3a4-eebe-4af2-adf8-aa41c11efacf', 'Ensure date and time is set automatically', 'PASS');
    }

    // check if root user is made disabled or not
    // for each devices, we request for their parameters from 'Get Device Parameters' API of Kandji
    // then we search for parameter with item_id = 1e4be748-e072-4c1f-b1ff-a98f076b8e8e which means
    // 'Disable the \root\ user', check for its status=PASS
    // https://github.com/kandji-inc/support/wiki/Devices-API---Parameter-Correlations
    // control will be implemented if implementationPercentage > 50
    public function getLocalAdminRestrictStatus(): ?string
    {
        return $this->getParameterStatus('1e4be748-e072-4c1f-b1ff-a98f076b8e8e', null, 'PASS');
    }

    // check if macOS updates is enabled
    // for each devices, we request for their parameters from 'Get Device Parameters' API of Kandji
    // then we search for parameter with item_id = 135b1c65-a665-431b-b919-39e984d6d29d which means
    // 'Report available macOS updates'
    // its status is usually 'WARNING' because Kandji directly won't able to update masOS itself, need permission
    // https://github.com/kandji-inc/support/wiki/Devices-API---Parameter-Correlations
    // control will be implemented if implementationPercentage > 50
    public function getTechVulnerabilitiesScanStatus(): ?string
    {
        return $this->getParameterStatus('135b1c65-a665-431b-b919-39e984d6d29d', null, null);
    }

    public static function getHowToImplementAction($action): ?string
    {
        $howToImplementActionsArr = [
            "getBlockedUsbStatus" => "",
            "getPasswordComplexityStatus" => "https://support.kandji.io/support/solutions/articles/72000560541-passcode-profiles",
            "getConditionalAccessStatus" => "",
            "getMobileDeviceStatus" => self::BLUEPRINT_URL,
            "getHddEncryptionStatus" => self::BLUEPRINT_URL,
            "getInactivityStatus" => self::BLUEPRINT_URL,
            "getAntivirusStatus" => self::BLUEPRINT_URL,
            "getNtpStatus" => self::BLUEPRINT_URL,
            "getLocalAdminRestrictStatus" => self::BLUEPRINT_URL,
            "getTechVulnerabilitiesScanStatus" => self::BLUEPRINT_URL,
        ];

        return array_key_exists($action, $howToImplementActionsArr) ? $howToImplementActionsArr[$action] : null;
    }
}
