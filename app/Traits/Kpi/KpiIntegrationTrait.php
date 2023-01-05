<?php

namespace App\Traits\Kpi;

use Illuminate\Support\Facades\Http;

trait KpiIntegrationTrait
{
    /**
     * getting device list form group ids ( Microsoft )
     * @param group_ids  
     * @param accessToken
     */
    public function getUniqueDevicesFromGroups($group_ids,$accessToken){
        $devices=[];
        $devices_id=[];
        // removing duplicate group ids
        $group_ids=array_unique($group_ids);
        foreach($group_ids as $group_id){
            $group_response = Http::withToken($accessToken)
            ->get("https://graph.microsoft.com/v1.0/groups/{$group_id}/members",[
                '$count' => 'true',
            ]);

            foreach($group_response['value'] as $group_data){
                if($group_data['@odata.type'] ==='#microsoft.graph.device'){
                    if(!in_array($group_data['id'],$devices_id)){
                        array_push($devices,$group_data);
                        array_push($devices_id,$group_data['id']);
                    }
                }
            }
        }

        return $devices;
    }

    private function handleCustomPolicyKpi($control)
    {
        $response = Http::withToken($this->provider->accessToken)
            ->get('https://graph.microsoft.com/beta/deviceManagement/deviceCompliancePolicies', [
                '$expand' => 'assignments,deviceStatusOverview',
            ]);

        if ($response->ok()) {
            $response = json_decode($response->body(), true);
            $group_list = [];
            foreach ($response['value'] as $res) {
                if ($res && array_key_exists('deviceCompliancePolicyScript', $res) && !is_null($res['deviceCompliancePolicyScript']) &&
                    array_key_exists('rulesContent', $res['deviceCompliancePolicyScript'])) {
                    $rule = json_decode(base64_decode($res['deviceCompliancePolicyScript']['rulesContent']));
                    foreach ($rule->Rules as $rule_check) {
                        if ($rule_check->SettingName === $control && array_key_exists('assignments', $res)) {
                            foreach ($res['assignments'] as $assignment) {
                                if (array_key_exists('target', $assignment) && array_key_exists('groupId', $assignment['target'])) {
                                    array_push($group_list, $assignment['target']['groupId']);
                                }
                            }
                            unset($assignment);
                        }
                    }
                }
            }
            unset($res);
            return json_encode([
                'passed' => count($this->getUniqueDevicesFromGroups($group_list,$this->provider->accessToken)),
                'total'=>$this->total_enabled_devices_count
            ]);
        }
        return null;
    }

}