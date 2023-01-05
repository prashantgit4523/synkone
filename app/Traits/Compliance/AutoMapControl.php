<?php

namespace App\Traits\Compliance;

use App\Models\Compliance\Project;
use Illuminate\Support\Facades\DB;
use App\Models\Compliance\Evidence;
use App\Models\Compliance\Standard;
use Illuminate\Support\Facades\Log;
use App\Models\Compliance\StandardControl;
use App\Models\Compliance\ComplianceStandardControlsMap;
use App\Http\Controllers\Compliance\ComplianceController;

trait AutoMapControl {

    /**
     * link implemented controls to new project
    */
    public function linkImplementedControl($project_to_map,$project){
        try{
            // blocking same standard map
            if($project_to_map->standard_id != $project->standard_id){
                $standard = Standard::find($project->standard_id);
                $standard_to_map = Standard::find($project_to_map->standard_id);
                $mapped_controls=$this->getMappedControl($standard,$standard_to_map);
                $mapped_controls_control_id=$this->getMappedControlWithControlId($mapped_controls);
                $manually_implemented_controls=$this->getManuallyImplementedControls($project_to_map);
                $this->linkControls($mapped_controls_control_id,$manually_implemented_controls,$project);
            }
        }
        catch(\Exception $e){
            Log::error('Control Mapping error :'.$e->getMessage());
        }
        
    }

    /**
     * get most implemented project
    */
    private function getMostImplementedProject(){
        return Project::with('complianceStandard')->first();
    }

    /**
     * get mapped control of most implemented project and new project standard
    */
    private function getMappedControl($standard,$standard_to_map){
        $mapped_controls_linked=ComplianceStandardControlsMap::with(['control','linked_control'])->whereHas('linked_control', function($q) use ($standard,$standard_to_map) {
            $q->where('standard_id',$standard->id);
                $q->orWhere('standard_id',$standard_to_map->id);
        })->get();
        $mapped_controls_control=ComplianceStandardControlsMap::with(['control','linked_control'])->whereHas('control', function($q) use ($standard,$standard_to_map) {
            $q->where('standard_id',$standard->id);
                $q->orWhere('standard_id',$standard_to_map->id);
        })->get();
        $mapped_controls=$mapped_controls_linked->toBase()->merge($mapped_controls_control);
        // $mapped_controls=ComplianceStandardControlsMap::get();
        try{
            foreach($mapped_controls as $key=> $mapped_control){
                $control=StandardControl::where('id',$mapped_control->control_id)->first();
                $linked_control=StandardControl::where('id',$mapped_control->linked_control_id)->first();
                if($control->standard_id==$standard->id && $linked_control->standard_id == $standard_to_map->id){
                    continue;
                }
                if($control->standard_id==$standard_to_map->id && $linked_control->standard_id == $standard->id){
                    continue;
                }
                unset($mapped_controls[$key]);
            }
        }
        catch(\Exception $e){
            Log::error('Control Mapping error :'.$e->getMessage());
        }
        
        return $mapped_controls->toArray();
    }

    private function getMappedControlWithControlId($controls){
        $data=[];
        foreach($controls as $control){
            try{
                $control_id=$control['control']['controlId'];
                $linked_control_id=$control['linked_control']['controlId'];
                if(array_key_exists($control_id,$data)){
                    if(!in_array($linked_control_id,$data[$control_id])){
                        array_push($data[$control_id],$linked_control_id);
                    }
                }
                else{
                    $data[$control_id]=[$linked_control_id];
                }
            }
            catch(\Exception $e){
                Log::error('Control Mapping error :'.$e->getMessage());
            }
        }
        return $data;
    }

    private function getManuallyImplementedControls($project_to_map){
        return $project_to_map->implementedControls()->where('automation', 'none')->get();
    }

    private function linkControls($mapped_controls_control_id,$manually_implemented_controls,$project){
        foreach($manually_implemented_controls as $control){
            $filtered_array=[];
            if(array_key_exists($control->controlId,$mapped_controls_control_id)){
                var_dump($mapped_controls_control_id[$control->controlId]);
                $this->updateEvidenceAndImplementControl($mapped_controls_control_id[$control->controlId],$control,$project);
            }
            else{
                foreach($mapped_controls_control_id as $key => $mcci){
                    if(in_array($control->controlId,$mcci)){
                        $this->updateEvidenceAndImplementControl([$key],$control,$project);
                    }
                }
            }
            // if(in_array($control->controlId,$mapped_controls_control_id)){
            //     $control_id=$control->controlId;
            //     $filtered_array = array_filter($mapped_controls_control_id, function($value) use ($control_id) {
            //         return $value !== $control_id;
            //     });
            //     var_dump($filtered_array);
            // }
        }
    }

    private function updateEvidenceAndImplementControl($control_array,$existing_control,$project){
        try{
            foreach($control_array as $control){
                $project_control=$project->controls()->where(DB::raw('CONCAT(primary_id, id_separator, sub_id)'), $control)->first();
                Evidence::create([
                    'project_control_id' => $project_control->id,
                    'name' => $existing_control->name,
                    'path' => $existing_control->id,
                    'type' => 'control',
                    'deadline' => $existing_control->deadline,
                    'status' => 'initial'
                ]);
                if($project_control->automation=="document" || $project_control->automation=="technical"){
                    $data=[
                        'status' => $existing_control->status,
                        'is_editable'=>$existing_control->is_editable,
                        'frequency' => $existing_control->frequency,
                        'deadline' => $existing_control->deadline,
                        'amend_status'=>null,
                        'automation'=>$existing_control->automation,
                        'manually_override'=>1,
                        'approver'=>$existing_control->approver,
                        'responsible'=>$existing_control->responsible,
                    ];
                }
                else{
                    $data=[
                        'status' => $existing_control->status,
                        'is_editable'=>$existing_control->is_editable,
                        'frequency' => $existing_control->frequency,
                        'deadline' => $existing_control->deadline,
                        'amend_status'=>null,
                        'approver'=>$existing_control->approver,
                        'responsible'=>$existing_control->responsible,
                    ];
                }
                
                // //Mirroring this projectControl with the linked projectControl
                $project_control->update($data);
            }
        }
        catch(\Exception $e){
            Log::error('Control Mapping error :'.$e->getMessage());
        }
        
        
    }
}