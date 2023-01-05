<?php

namespace App\Traits\Integration;

use App\Models\Integration\IntegrationControl;
use App\Models\Integration\IntegrationProvider;
use GuzzleHttp\Client;
use RecursiveArrayIterator;
use RecursiveIteratorIterator;
use App\Models\Compliance\Evidence;
use App\Models\Integration\Integration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Session;
use App\Models\Compliance\ProjectControl;
use App\Models\Compliance\StandardControl;

trait IntegrationApiTrait
{
    use TokenValidateTrait;

    public function getProviderByIntegrationSlug($slug)
    {
        $integration = Integration::where('slug', $slug)->first();

        if ($integration && $integration->implemented_integration) {
            return $integration->provider;
        } else {
            return false;
        }
    }

    //sets connected in integration table
    public function connectIntegration($slug)
    {
        $integration = Integration::where('slug', $slug)->first();

        //unset service_name from session
        Session::forget('integration_service_name_' . tenant('id'));
        Session::forget('integration_domain_state_' . tenant('id'));

        if ($integration) {
            $integration->update(['connected' => 1]);

            callArtisanCommand('technical-control:api-map');
            callArtisanCommand('kpi_controls:update');

            return $integration->name;
        }
        return false;
    }

    public function mapApiToControls($formattedResponse, $controlMapping, $provider, $removeImplementation = false)
    {
        $formattedResponse = $this->removeSensitiveInfoFromId(json_decode($formattedResponse));
        $providers = IntegrationProvider::pluck('name', 'id');

        StandardControl::where('standard_id', $controlMapping['standard_id'])
            ->where('primary_id', $controlMapping['primary_id'])
            ->where('sub_id', $controlMapping['sub_id'])
            ->update([
                'automation' => 'technical',
            ]);

        IntegrationControl::query()
            ->where('primary_id', $controlMapping['primary_id'])
            ->where('sub_id', $controlMapping['sub_id'])
            ->where('standard_id', $controlMapping['standard_id'])
            ->update(['last_implemented_by' => !$removeImplementation ? $provider : null]);

        //get all controls for this implementation mapping. Exclude ones that have automation override.
        ProjectControl::query()
            ->whereHas('project.of_standard', fn($q) => $q->where('id', $controlMapping['standard_id']))
            ->where('primary_id', $controlMapping['primary_id'])
            ->where('sub_id', $controlMapping['sub_id'])
            ->with(['evidences', 'project'])
            ->where('manual_override', 0)
            ->whereNull('unlocked_at')
            ->each(function ($control) use ($provider, $providers, $formattedResponse, $removeImplementation) {
                if($control->automation != "technical" && $control->status === "Implemented"){
                    return;
                }
                $this->warn(sprintf('Using \'%s\'', $providers[$provider]));

                if ($removeImplementation) {
                    $this->info(sprintf('Removed Implementation [%s] because response is null and it was already implemented', $control->controlId));
                    $this->removeImplementation($control);
                    return;
                }

                if (!empty($formattedResponse)) {
                    $this->info(sprintf('Implemented [%s]', $control->controlId));
                    $this->implementControl($control, $formattedResponse);
                } else {
                    $this->info(sprintf('Control [%s] set to technical, but wasn\'t implemented', $control->controlId));
                    $control->update([
                        'automation' => "technical",
                        'deadline' => $control->deadline ?? now()->addDays(7)->format('Y-m-d'),
                        'frequency' => 'One-Time',
                        'responsible' => $control->responsible ?: $control->project->admin_id
                    ]);
                }
            });
    }

    private function handleCustomPolicy($responseArr, $requiredFields)
    {
        $requiredFields = json_decode($requiredFields, true);

        foreach ($responseArr as $response) {
            if (!array_key_exists("deviceCompliancePolicyScript", $response)) {
                continue;
            }

            $rulesContentString = $response["deviceCompliancePolicyScript"] != null ? $response["deviceCompliancePolicyScript"]["rulesContent"] : null;

            if ($rulesContentString) {
                $ruleContent = $this->getPowershellScriptName($rulesContentString);
                if ($requiredFields['SettingName'] != $ruleContent) {
                    continue;
                }

                $deviceStatusOverview = $response["deviceStatusOverview"];

                //remove the data we don't need from the array
                unset($deviceStatusOverview["id"]);
                unset($deviceStatusOverview["lastUpdateDateTime"]);
                unset($deviceStatusOverview["configurationVersion"]);

                $totalAssignedDevices = array_sum($deviceStatusOverview);
                $successCount = $deviceStatusOverview['successCount'];

                // This policy was not assigned to any device
                if ($totalAssignedDevices === 0) {
                    continue;
                }

                $implementationPercentage = ($successCount / $totalAssignedDevices) * 100;
                if ($implementationPercentage > 50) {
                    $results = [];
                    $results["requiredSettings"] = json_decode(base64_decode($rulesContentString), true);
                    $results["devicesStatuses"] = $deviceStatusOverview;
                    return $results;
                }
            }
        }

        return null;
    }

    private function formatResponse($response, $requiredFields, $additionalFields, $filterOperator, $subId = null)
    {
        // code to get check the requiredFields in response
        $additionalDetails = $additionalFields;
        if(!empty($requiredFields))
            $implementedEvidence = $this->recursiveArrayIterator($response, $requiredFields, $additionalDetails, $filterOperator, $subId);
        else
            $implementedEvidence = $response;

        $implementedEvidence = array_values($implementedEvidence);

        $requiredKeys = array_keys((array)$requiredFields);

        $additionalDetails = array_merge($additionalDetails, $requiredKeys);

        $controlEvidence = array();

        foreach ($implementedEvidence as $evidence) {
            $details = array();
            foreach ($additionalDetails as $jsonDetails) {
                if ($jsonDetails === 'includeApplications') {
                    $details[$jsonDetails] = $evidence['conditions']['applications'][$jsonDetails];
                } else {
                    $details[$jsonDetails] = $evidence[$jsonDetails];
                }
            }
            $controlEvidence[] = $details;
        }

        return $controlEvidence;
    }

    private function implementControl($control, $jsonResponse)
    {
        $control->automation = "technical";
        $control->deadline = $control->status === "Not Implemented" ? date('Y-m-d') : $control->deadline;
        $control->status = "Implemented";
        $control->frequency = 'One-Time';
        $control->responsible = $control->responsible ?: $control->project->admin_id;
        $savedControl = $control->save();
        
        $evidences = $control->evidences();
        $jsonEvidence = $evidences->where('type', 'json')->first();
        
        if (!$jsonEvidence) {
            $jsonEvidence = new Evidence();
        }

        $jsonEvidence->project_control_id = $control->id;
        $jsonEvidence->name = "Automated control";
        $jsonEvidence->text_evidence = json_encode($jsonResponse);
        $jsonEvidence->path = "json evidence";
        $jsonEvidence->type = 'json';
        $jsonEvidence->deadline = null;
        $jsonEvidence->status = 'approved';
        $jsonEvidence->save();

        return $savedControl && $jsonEvidence;
    }

    private function removeImplementation($control)
    {
        $evidences = $control->evidences();
        $jsonEvidence = $evidences->where('type', 'json')->first();
        $jsonEvidence?->delete();

        $control->deadline = $control->status === "Implemented" ? date('Y-m-d', strtotime('+7 days')) : $control->deadline;
        $control->status = "Not Implemented";
        $control->automation = "technical";
        $control->frequency = 'One-Time';
        $control->responsible = $control->responsible ?: $control->project->admin_id;
        $control->save();
    }

    private function compareData($val1, $val2, $operator)
    {
        switch ($operator) {
            case "=":
                $condition = $val1 == $val2;
                break;
            case ">=":
                $condition = $val1 >= $val2;
                break;
            case ">":
                $condition = $val1 > $val2;
                break;
            case "<=":
                $condition = $val1 <= $val2;
                break;
            case "<":
                $condition = $val1 < $val2;
                break;
            case "!=":
                $condition = $val1 != $val2;
                break;
            default:
                $condition = false;
        }
        return $condition;
    }

    private function recursiveArrayIterator($array, $requiredFields, $additionalDetails, $filterOperator, $subId = null)
    {
        $outputArray = array();
        $arrIt = new RecursiveIteratorIterator(new RecursiveArrayIterator($array));

        foreach ($arrIt as $sub) {
            $returnSubArrayCopy = 0;
            $subArray = $subArrayCopy = iterator_to_array($arrIt->getSubIterator());

            if (!in_array($subId, ['6.2.2']) && !in_array('assignments', $additionalDetails)) {
                $subArray = $this->array_flatten($subArray);
            }

            $conditionsMet = 0;

            foreach ($requiredFields as $rKey => $rVal) {
                if ($rKey === 'includeApplications' && $subArrayCopy['conditions']['applications'][$rKey]) {
                    $commonValue = array_intersect($subArrayCopy['conditions']['applications'][$rKey], $rVal);

                    if (count($commonValue) > 0) {
                        $conditionsMet++;
                    }
                    $returnSubArrayCopy = 1;
                }
                if (array_key_exists($rKey, $subArray) && $this->compareData($subArray[$rKey], $rVal, $filterOperator)) {
                    $conditionsMet++;
                }
            }

            if ($subId === '6.2.2') {
                $assignmentCheck = (!empty($subArray['conditions']['users']['includeUsers']) || !empty($subArray['conditions']['users']['includeGroups']) || !empty($subArray['conditions']['users']['includeRoles']));
            } elseif (in_array('assignments', $additionalDetails)) {
                $assignmentCheck = !empty($subArray['assignments']);
            } else {
                $assignmentCheck = true;
            }


            if ($conditionsMet === count($requiredFields) && $assignmentCheck) {
                $outputArray[] = $returnSubArrayCopy ? $subArrayCopy : $subArray;
            }
        }

        return $outputArray;
    }

    private function array_flatten($array)
    {
        if (!is_array($array)) {
            return FALSE;
        }
        $result = array();
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result = array_merge($result, $this->array_flatten($value));
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    private function removeSensitiveInfoFromId($array)
    {
        if (is_array($array)) {
            $newArray = [];

            foreach ($array as $arr) {
                if (isset($arr->id) && strpos($arr->id, '/subscriptions') !== false) {
                    $arr->id = explode('/', $arr->id, 4)[3];
                    $newArray[] = $arr;
                }
            }

            return count($newArray) ? $newArray : $array;
        }
        return $array;
    }

    private function getPowershellScriptName($token)
    {
        $tokenHeader = base64_decode($token);
        $jwtHeader = json_decode($tokenHeader);

        return $jwtHeader->Rules[0]->SettingName;
    }
}
