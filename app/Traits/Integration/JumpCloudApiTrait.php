<?php

namespace App\Traits\Integration;

use Carbon\Carbon;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

trait JumpCloudApiTrait
{
    //Starting point for all JumpCloud API calls with policy
    //Tempname is the name of the policy template
    //Compliance is the conditional check on how to test this policy
    //config is the name of the condition required to be true
    //kpi is to differentiate between the policy integration and kpi
    private function getPolicyData($tempName, $compliance, $config = false, $kpi = false): bool|string|null
    {
        try {
            $dataToReturn = false;
            //get policy Ids both created and implemented
            $policyIds = $this->getPolicyIdsWithDetail($tempName, $compliance, $config);
            $createdPolicy = $policyIds['createdPolicy'];
            $implementedPolicy = Arr::pluck($policyIds['implementedPolicy'], 'policy');
            if ($implementedPolicy) {
                //get implementation rate for each policy
                $kpiData = $this->getKpiData($createdPolicy, $implementedPolicy);
                if ($kpiData && $kpi) {
                    $passed = $kpiData['kpi']['passed'];
                    $total = $kpiData['kpi']['total'];
                    if ($passed && $total) {
                        return json_encode([
                            'passed' => $passed,
                            'total' => $total,
                        ], true);
                    }
                }
                if ($kpiData && !$kpi && $kpiData['implementationRate'] > 50) {
                    $dataToReturn = $this->formatPolicyResult($policyIds['implementedPolicy']);
                    $dataToReturn = [
                        'policies' => $dataToReturn,
                        'implementationProof' => $kpiData['implementationProof'] ?? null,
                        'implementationRate' => $kpiData['implementationRate'] . '%',
                    ];
                }
            }
            return $dataToReturn ? json_encode($dataToReturn) : null;
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with getPolicyData on JumpCloud Api Trait');
        }
        return null;
    }

    //gets policy ids with details for created and implemented policies
    private function getPolicyIdsWithDetail($tempName, $compliance, $config = false): ?array
    {
        try {
            $createdPolicy = [];
            $implementedPolicy = [];
            //check each policy if it is created or implemented based on $config
            foreach ($tempName as $temp) {
                $policyRes = $this->client->get('v2/policies', [
                    'filter' => 'template.id:eq:' . $temp,
                ]);
                if ($policyRes->ok()) {
                    $policy = json_decode($policyRes->body(), true);
                    foreach ($policy as $singlePolicy) {
                        $os = $singlePolicy['template']['osMetaFamily'];
                        array_push($createdPolicy, [
                            'id' => $singlePolicy['id'],
                            'os' => $os === 'darwin' ? 'mac' : $os,
                        ]);
                        $compliant = $this->checkCompliance($singlePolicy, $compliance, $config);

                        if ($compliant) {
                            $policyDetails = $this->getPolicyDetails($singlePolicy);
                            $dataToReturn = [
                                'policy' => [
                                    'id' => $compliant['id'],
                                    'os' => $compliant['os'],
                                ],
                                'policiesDetails' => [
                                    'policy' => $policyDetails,
                                    'configuration' => $compliant['complianceDetails'],
                                ],
                            ];
                            array_push($implementedPolicy, $dataToReturn);
                        }
                    }
                    if (!$policy) {
                        $policy = $this->getEmptyPolicyData($temp);
                        if ($policy) {
                            $os = $policy[0]['osMetaFamily'];
                            array_push($createdPolicy, [
                                'id' => $policy[0]['id'],
                                'os' => $os === 'darwin' ? 'mac' : $os,
                            ]);
                        }
                    }
                }
            }
            $dataToReturn = [
                'createdPolicy' => $createdPolicy,
                'implementedPolicy' => $implementedPolicy,
            ];
            return $dataToReturn ? $dataToReturn : null;
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with getPolicyIdsWithDetail on JumpCloud Api Trait');
        }

        return null;
    }

    //checks where the ids passed are complaint to the conditions
    private function checkCompliance($policy, $compliance, $config): bool|array|null
    {
        try {
            $passed = [];
            $createdPolicy = $this->client->get('v2/policies/' . $policy['id']);

            if ($createdPolicy->ok()) {
                $createdPolicy = json_decode($createdPolicy->body(), true);
                $os = $createdPolicy['template']['osMetaFamily'];
                $configurations = collect($createdPolicy['values']);
                if ($config) {
                    if ($compliance === 'Inactivity') {
                        $compliantPolicies = $configurations
                            ->whereIn('configFieldName', $config)
                            ->filter(function ($item) {
                                if ($item['value'] <= 900) {
                                    return $item;
                                }
                            })->toArray();
                        $condition = count($compliantPolicies) === 1;
                    }
                    if ($compliance === 'configTrue') {
                        $compliantPolicies = $configurations
                            ->whereIn('configFieldName', $config)
                            ->filter(function ($item) {
                                if ($item['value'] === true) {
                                    return $item;
                                }
                            })->toArray();
                        $condition = count($compliantPolicies) === 1;
                    }
                    if ($condition) {
                        $passed = [
                            'status' => true,
                            'id' => $createdPolicy['id'],
                            'os' => $os === 'darwin' ? 'mac' : $os,
                            'complianceDetails' => array_values($compliantPolicies),
                        ];
                    } else {
                        $passed['status'] = false;
                    }
                } else {
                    if ($compliance === 'enabled') {
                        $passed = [
                            'status' => true,
                            'id' => $createdPolicy['id'],
                            'os' => $os === 'darwin' ? 'mac' : $os,
                            'complianceDetails' => [
                                'config' => 'enable and enforce',
                            ],
                        ];
                    }
                }
            }
            return $passed['status'] ? $passed : false;
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with checkCompliance on JumpCloud Api Trait');
        }

        return null;
    }

    //get formatted policy details
    private function getPolicyDetails($policy): array|null
    {
        try {
            return [
                'id' => $policy['id'],
                'name' => $policy['name'],
                'templateName' => $policy['template']['displayName'],
                'templateId' => $policy['template']['id'],
                'displayName' => $policy['template']['displayName'],
                'description' => $policy['template']['description'],
            ];
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with getPolicyDetails on JumpCloud Api Trait');
        }

        return null;
    }

    //get KPi format Data with implementation level to test if it has more than 60% implementation rate
    private function getKpiData($createdPolicy, $implementedPolicy): ?array
    {
        try {
            $totalDevices = $this->getTotalDevices();
            $createdOs = Arr::pluck($createdPolicy, 'os');
            $total = collect($totalDevices['devices'])->whereIn('os', $createdOs)->sum('count');
            $passedArr = $this->getPolicyCreatedDevices($implementedPolicy);
            $passed = collect($passedArr)->count();
            if ($total > 0) {
                $implementationRate = round(($passed / $total) * 100);
            }
            return [
                'kpi' => [
                    'passed' => $passed,
                    'total' => $total,
                ],
                'implementationRate' => $implementationRate ?? null,
                'implementationProof' => array_slice($passedArr, 0, 1),
            ];
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with getKpiData on JumpCloud Api Trait');
        }

        return null;
    }

    //this is used to get Total no of devices where the policy can be implemented
    private function getTotalDevices(): ?array
    {
        try {
            $all_devices = $this->client->get('systems');
            if ($all_devices->ok()) {
                $all_devices_body = json_decode($all_devices->body(), true);
                $totalCount = $all_devices_body['totalCount'];
                if ($totalCount) {
                    $device_collection = collect($all_devices_body['results'])->groupBy('osFamily');
                    $device_family['devices'] = [];
                    foreach ($device_collection as $key => $deviceFamilia) {
                        array_push($device_family['devices'], [
                            'os' => $key === 'darwin' ? 'mac' : $key,
                            'count' => count($deviceFamilia),
                        ]);
                    }
                    $data_to_return = [
                        'totalCount' => $totalCount,
                        'devices' => $device_family['devices'],
                    ];
                    return $data_to_return ? $data_to_return : null;
                }
            }
            return null;
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with getTotalDevices on JumpCloud Api Trait');
        }

        return null;
    }

    //gets device info from Ids where policy can be implemented
    private function getPolicyCreatedDevices($policiesId): ?array
    {
        try {
            return Arr::flatten(array_map(function ($policyId) {
                return collect($this->policyResult['allComplaint'])->where('policyID', $policyId['id']);
            }, $policiesId), 1);
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with getPolicyCreatedDevices on JumpCloud Api Trait');
        }

        return null;
    }

    //formatting Policy details to return
    private function formatPolicyResult($implementedPolicy): ?array
    {
        try {
            $dataToReturn = [];
            foreach ($implementedPolicy as $policy) {
                array_push($dataToReturn, [
                    'policyDetail' => $policy['policiesDetails']['policy'],
                    'configuration' => $policy['policiesDetails']['configuration'],
                ]);
            }
            return $dataToReturn ? $dataToReturn : null;
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with formatPolicyResult on JumpCloud Api Trait');
        }

        return null;
    }

    //this is a function for getting Command/Script details
    //ScriptName is the name of the script to be executed
    //Kpi is for kpi format data
    private function getScriptPolicyData($scriptName, $kpi = false): bool|string|null
    {
        try {
            $script = $this->getScriptPolicy($scriptName);
            $dataToReturn = [];
            if ($script) {
                //get policy result for each script
                $policyResults = $this->getAllPolicyResults($script, $scriptName);
                $getTotalDevices = $this->getTotalDevices();
                if ($policyResults && $getTotalDevices) {
                    $kpiData = $this->getKpiFormatData($policyResults, $getTotalDevices);
                    if ($kpiData) {
                        if ($kpi) {
                            $passed = $kpiData['kpi']['passed'];
                            $total = $kpiData['kpi']['total'];
                            if ($total > 0 && $passed > 0) {
                                return json_encode([
                                    'passed' => $passed,
                                    'total' => $total,
                                ], true);
                            }
                            $dataToReturn = $kpiData['kpi'];
                        } else if ($kpiData['implementationRate'] > 50) {
                            foreach ($script as $soloScript) {
                                array_push($dataToReturn, [
                                    'id' => $soloScript['id'],
                                    'name' => $soloScript['name'],
                                    'template_name' => $scriptName,
                                    'osMetaFamily' => $soloScript['commandType'],
                                ]);
                            }
                            $dataToReturn = [
                                'policy_detail' => $dataToReturn,
                                'implementationRate' => $kpiData['implementationRate'],
                            ];
                        }
                        return $dataToReturn ? json_encode($dataToReturn, true) : null;
                    }
                }
            }
            return null;
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with getScriptPolicyData on JumpCloud Api Trait');
        }

        return null;
    }

    //get Script Data from Command Api
    private function getScriptPolicy($scriptName): ?array
    {
        try {
            $policy_response = $this->client->get('commands');
            if ($policy_response->ok()) {
                $policy_bodys = json_decode($policy_response, true)['results'];
                $policies = [];
                foreach ($policy_bodys as $policy) {
                    if (Str::contains($policy['command'], $scriptName)) {
                        array_push($policies, [
                            'id' => $policy['id'],
                            'name' => $policy['name'],
                            'schedule' => $policy['schedule'],
                            'scheduleRepeatType' => $policy['scheduleRepeatType'],
                            'commandType' => $policy['commandType'],
                        ]);
                    }
                }
                return $policies ? $policies : null;
            }
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with getScriptPolicy on JumpCloud Api Trait');
        }

        return null;
    }

    //filter Script Policy Result based on condition
    //here we are taking data for last 60 mins from the latest record
    private function getAllPolicyResults($scriptsData, $scriptName): ?array
    {
        try {
            $dataToReturn = [];
            foreach ($scriptsData as $scriptData) {
                $primaryResults = collect(json_decode($this->client->get('commands/' . $scriptData['id'] . '/results'), true));
                $result = collect($primaryResults->map(function ($result) {
                    $result['requestTime'] = Carbon::parse($result['requestTime'])->format('Y-m-d H:i:s');
                    $result['response']['data']['output'] = json_decode($result['response']['data']['output'], true);
                    return $result;
                }));
                //reduce 60 mins from request time to get start time of script execution from last data
                $latestExecutionData = $result->last()['requestTime'];
                $startTime = Carbon::parse($latestExecutionData)->subMinutes(60)->format('Y-m-d H:i:s');
                $passed = $result->where('requestTime', '>=', $startTime)->where('requestTime', '<=', $latestExecutionData)
                    ->where('response.data.output.' . $scriptName, true)->unique('system')->count();
                if ($passed) {
                    array_push($dataToReturn, [
                        'os' => $scriptData['commandType'],
                        'passed' => $passed,
                    ]);
                }
            }
            return $dataToReturn ? $dataToReturn : null;
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with getAllPolicyResults on JumpCloud Api Trait');
        }

        return null;
    }

    //formatting Data in KPi format
    private function getKpiFormatData($policyResults, $getTotalDevices): ?array
    {
        try {
            $finalTotal = 0;
            $finalPassed = 0;
            foreach ($policyResults as $policyResult) {
                $finalTotal = $finalTotal + collect($getTotalDevices['devices'])->where('os', $policyResult['os'])->first()['count'];
                $finalPassed = $finalPassed + $policyResult['passed'];
            }
            if ($finalTotal > 0) {
                return [
                    'kpi' => [
                        'passed' => $finalPassed,
                        'total' => $finalTotal,
                    ],
                    'implementationRate' => round(($finalPassed / $finalTotal) * 100, 2),
                ];
            }
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with getKpiFormatData on JumpCloud Api Trait');
        }
        return null;
    }
    //this is to get Data from Policy Template which is not created
    //we need this as we need data for which Os this policy belongs to and that OS has
    //eg:
    //there are two policies windows and mac,then we send the names of both the polices
    //the first Api call gets ids from the policies that are created (windows)
    //then we only get windows data,And thus the total no of users will be based on only Windows user
    // but Mac users can also have this type of policy implemented
    //and this function gets Data from Policy Template Which is not created
    private function getEmptyPolicyData($temp): ?array
    {
        try {
            $policyRes = $this->client->get('v2/policytemplates?limit=1', [
                'filter' => 'id:eq:' . $temp,
            ]);
            if ($policyRes->ok()) {
                return json_decode($policyRes, true);
            }
            return null;
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with getEmptyPolicyData on JumpCloud Api Trait');
        }

        return null;
    }

    //gets one implemeneted policy Data
    private function getOneImplementedPolicy(): ?string
    {
        try {
            $response = $this->client->get('v2/policies?sort=template.name');
            if ($response->ok()) {
                //check if policy is implemented
                $allPolicies = json_decode($response->body(), true);
                if ($allPolicies) {
                    foreach ($allPolicies as $policy) {
                        $policy_implemented_devices = $this->client->get('v2/policies/' . $policy['id'] .
                            '/systems?details=v1');
                        if ($policy_implemented_devices->ok()) {
                            $policy_implemented_devices = json_decode($policy_implemented_devices->body(), true);
                            if (count($policy_implemented_devices) > 0) {
                                return json_encode([
                                    'id' => $policy['id'],
                                    'name' => $policy['template']['name'],
                                    'display_name' => $policy['template']['displayName'],
                                    'state' => $policy['template']['state'],
                                    'os_family' => $policy['template']['osMetaFamily'],
                                    // 'policy_implemented_devices' => count($policy_implemented_devices),
                                ], true);
                            }
                        }
                    }
                }
            }

            return null;
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with getOneImplementedPolicy on JumpCloud Api Trait');
        }

        return null;
    }

    //gets One Created Policy Data
    private function getOneCreatedPolicy(): ?string
    {
        try {
            $response = $this->client->get('v2/policies?limit=1&sort=template.name');
            if ($response->ok()) {
                $apiResponse = json_decode($response->body(), true);
                $required_values = [];
                $additional_values = ['displayName', 'id', 'state', 'behavior', 'description', 'osMetaFamily'];
                $filter_operator = '=';
                return json_encode($this->formatResponse(
                    $apiResponse,
                    $required_values,
                    $additional_values,
                    $filter_operator
                )[1]);
            }
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with getOneCreatedPolicy on JumpCloud Api Trait');
        }
        return null;
    }

    //getting all devices and the policies that are implemented
    //device should be active within last 3 months
    //since there are alot of Api calls,we call them concurrently
    private function getAllDevicesWithPolicies(): ?array
    {
        try {
            $allDevices = $this->client->get('systems', [
                'fields' => ['id', 'osFamily', 'active', 'lastContact'],
            ]);
            if ($allDevices->ok()) {
                $devices = json_decode($allDevices->body(), true)['results'];
                $today = Carbon::today()->subMonths(3)->toDateString();
                $devices = array_filter($devices, function ($device) use ($today) {
                    if (Carbon::parse($device['lastContact'])->toDateString() > $today) {
                        return $device;
                    }
                });
                $allDeviceRes = Http::pool(fn(Pool $pool) => array_map(fn($device) => $pool->withHeaders(['x-api-key' => $this->api_key])
                    ->get('https://console.jumpcloud.com/api/v2/systems/' . $device['id'] . '/policystatuses'), $devices));
            }

            $allAssignedpolicies = collect(array_map(function ($device) {
                if ($device->ok()) {
                    return json_decode($device, true);
                }
            }, $allDeviceRes))->flatten(1)->toArray();
            $allComplaint = array_filter($allAssignedpolicies, function ($policy) {
                if ($policy['success']) {
                    return $policy;
                }
            });
            $nonCompliant = array_filter($allAssignedpolicies, function ($policy) {
                if (!$policy['success']) {
                    return $policy;
                }
            });
            return [
                'allComplaint' => $allComplaint,
                'nonCompliant' => $nonCompliant,
                'totalApplied' => $allAssignedpolicies,
            ];
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with getAllDevicesWithPolicies on JumpCloud Api Trait');
        }
        return null;
    }
}
