<?php

namespace App\Traits\Integration;

trait KandjiApiTrait
{
    private function getDevices() {
        $devicesResponse = $this->client->get(self::ALL_DEVICES);

        if ($devicesResponse->ok()) {
            return json_decode($devicesResponse->body(), true);
        }

        return null;
    }

    private function getLibraryItems($deviceId) {
        $deviceLibraryResponse = $this->client->get(self::DEVICES.$deviceId.'/library-items');

        if ($deviceLibraryResponse->ok()) {
            $deviceDetails = json_decode($deviceLibraryResponse->body(), true);

            $passcode_status = [];
            if (!empty($deviceDetails) && !empty($deviceDetails['library_items'])) {

                $libraries = $deviceDetails['library_items'];
                foreach($libraries as $library)
                {
                    // if(str_contains($library['name'], 'passcode'))   // for PHP >= 8.0
                    if (strpos(strtolower($library['name']), 'passcode') !== false && strtolower($library['status']) == 'success')
                    {
                        $data = [];
                        $data['id'] = $library['id'];
                        $data['name'] = $library['name'];
                        $data['status'] = $library['status'];
                        $data['type'] = $library['type'];
                        array_push($passcode_status, $data);
                    }
                }
            }
            if(!empty($passcode_status))
            {
                return $passcode_status;
            }
        }

        return null;
    }

    private function getParameterStatus($itemId, $name = null, $status = null): bool|string|null
    {
        try {
            $devicesResponse = $this->client->get('/devices');

            if ($devicesResponse->ok()) {
                $devices = json_decode($devicesResponse->body(), true);
                $total = 0;
                $passed = 0;
                $data_to_return = [];

                foreach ($devices as $device) {
                    $responseData = $this->parametersCheck($device, $itemId, $name, $status);
                    if(!empty($responseData))
                    {
                        $total++;
                        if($responseData['passed'] != 0)
                        {
                            $passed++;
                            array_push($data_to_return, $responseData['passed_data']);
                        }
                    }
                }

                $implementationPercentage = ($passed / $total) * 100;
                if($total > 0 && $implementationPercentage > 50)
                {
                    return json_encode($data_to_return);
                }
            }

            return null;
        } catch (\Exception$th) {
            writeLog('error', 'KandjiTrait has a issue: '.$th->getMessage());
            return null;
        }
    }

    private function parametersCheck($device, $itemId, $name, $status)
    {
        if($device['is_removed'] === false)
        {
            $deviceDetailResponse = $this->client->get(self::DEVICES.$device['device_id'].'/parameters');

            $deviceDetails = json_decode($deviceDetailResponse->body(), true);
            $dataList = [];
            $passed = 0;

            if ($deviceDetailResponse->ok() && !empty($deviceDetails) && !empty($deviceDetails['parameters']))
            {
                $parameters = $deviceDetails['parameters'];
                foreach($parameters as $parameter)
                {
                    $parameterCheck = $this->setParameterStatus(false, $itemId, $status, $name, $parameter);

                    if($parameterCheck)
                    {
                        $paramData = [];
                        $paramData['item_id'] = $parameter['item_id'];
                        $paramData['name'] = $parameter['name'];
                        $paramData['status'] = $parameter['status'];
                        $paramData['category'] = $parameter['category'];
                        $paramData['subcategory'] = $parameter['subcategory'];
                        array_push($dataList, $paramData);

                        break;
                    }
                }
            }

            if (count($dataList)) {
                $passed++;

                $kandji_devices = [];
                $kandji_devices['device_id'] = $device['device_id'];
                $kandji_devices['device_name'] = $device['device_name'];
                $kandji_devices['data'] = $dataList;
            }

            return ['passed' => $passed, 'passed_data' => $kandji_devices];
        }

        return null;
    }

    private function setParameterStatus($parameterCheck, $itemId, $status, $name, $parameter)
    {
        if(!is_null($status) && !is_null($name) && ($parameter['item_id'] == $itemId || $parameter['name'] == $name) && strtoupper($parameter['status']) == $status)
        {
            $parameterCheck = true;
        }
        if(!is_null($status) && $parameter['item_id'] == $itemId && strtoupper($parameter['status']) == $status)
        {
            $parameterCheck = true;
        }
        if(!is_null($name) && $parameter['item_id'] == $itemId && strtoupper($parameter['name']) == $name)
        {
            $parameterCheck = true;
        }
        if(!is_null($itemId) && $parameter['item_id'] == $itemId && is_null($status) && is_null($status))
        {
            $parameterCheck = true;
        }

        return $parameterCheck;
    }

    private function hddEncryptedDevices($device)
    {
        if($device['is_removed'] === false)
        {
            $deviceDetailResponse = $this->client->get(self::DEVICES.$device['device_id'].'/details');
            return $this->deviceDetail($deviceDetailResponse, $device);
        }
        return null;
    }

    private function deviceDetail($deviceDetailResponse, $device)
    {
        $device_hdd_encryption_status = [];
        $total_disks = 0;
        $encrypted_disks = 0;
        $passed = 0;
        $kandji_devices = [];

        if ($deviceDetailResponse->ok()) {
            $deviceDetails = json_decode($deviceDetailResponse->body(), true);

            if(!empty($deviceDetails['volumes']))
            {
                $volumes = $deviceDetails['volumes'];
                foreach($volumes as $volume)
                {
                    $disk = [];
                    $disk['name'] = $volume['name'];
                    $disk['format'] = $volume['format'];
                    $disk['percent_used'] = $volume['percent_used'];
                    $disk['capacity'] = $volume['capacity'];
                    $disk['available'] = $volume['available'];
                    $disk['encrypted'] = $volume['encrypted'];
                    array_push($device_hdd_encryption_status, $disk);

                    $total_disks++;
                    if(strtolower(strtolower($volume['encrypted'])) == 'yes')
                    {
                        $encrypted_disks++;
                    }
                }
            }

            if ($encrypted_disks != 0) {
                $passed++; // sum of devices with encrypted disk

                $kandji_devices['device_id'] = $device['device_id'];
                $kandji_devices['device_name'] = $device['device_name'];
                $kandji_devices['disks'] = $device_hdd_encryption_status;
                $kandji_devices['total_disks'] = $total_disks;
                $kandji_devices['encrypted_disks'] = $encrypted_disks;
            }

            return ['passed' => $passed, 'kandji_devices' => $kandji_devices];
        }

        return null;
    }

    private function antivirusApps($device)
    {
        if($device['is_removed'] === false)
        {
            $deviceAppResponse = $this->client->get(self::DEVICES.$device['device_id'].'/apps');

            if ($deviceAppResponse->ok()) {
                $appDetails = json_decode($deviceAppResponse->body(), true);
                $app_status = [];

                if(!empty($appDetails['apps']))
                {
                    // List of antivirus name without space or special characters in-between
                    $antivirus = [
                        'avast',
                        'intego',
                        'bitdefender',
                        'norton',
                        'malwarebytes',
                        'kaspersky',
                        'avira',
                        'totalav',
                        'mcafee',
                        'sophos',
                        'eset',
                        'kaspersky',
                        'lookout',
                        'fyde',
                        'clamxav',
                        'vipre',
                        'webroot',
                        'mackeeper',
                        'clario'
                    ];

                    // List of antivirus name with space or special characters in-between
                    $antivirus2 = [
                        'f-secure',
                        'trend micro',
                        'microsoft defender'
                    ];

                    $apps = $appDetails['apps'];
                    $appLoopResponse = $this->antivirusAppsLoop($apps, $antivirus, $antivirus2);
                    if(!empty($appLoopResponse))
                    {
                        array_push($app_status, $appLoopResponse);
                    }
                }

                if (count($app_status)) {
                    $kandji_devices = [];
                    $kandji_devices['device_id'] = $device['device_id'];
                    $kandji_devices['device_name'] = $device['device_name'];
                    $kandji_devices['antivirus_app'] = $app_status;

                    return ['kandji_devices' => $kandji_devices];
                }

                return ['kandji_devices' => []];
            }
        }

        return null;
    }

    private function antivirusAppsLoop($apps, $antivirus, $antivirus2)
    {
        $app_status = null;
        foreach($apps as $app)
        {
            $appName = str_replace('-', ' ', strtolower($app['app_name']));
            $app_name = str_replace('.', ' ', $appName);
            if(array_intersect(explode(' ', $app_name), $antivirus) != null)
            {
                $data = [];
                $data['app_id'] = $app['app_id'];
                $data['app_name'] = $app['app_name'];
                $data['source'] = $app['source'];
                $data['version'] = $app['version'];
                $app_status = $data;
                break;
            }
            else
            {
                foreach($antivirus2 as $software)
                {
                    if(str_contains(strtolower($app['app_name']), $software))
                    {
                        $data = [];
                        $data['app_id'] = $app['app_id'];
                        $data['app_name'] = $app['app_name'];
                        $data['source'] = $app['source'];
                        $data['version'] = $app['version'];
                        $app_status = $data;
                        break;
                    }
                }
                if($app_status != null)
                {
                    break;
                }
            }
        }

        return $app_status;
    }
}
