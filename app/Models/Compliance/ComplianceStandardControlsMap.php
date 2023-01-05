<?php

namespace App\Models\Compliance;

use Illuminate\Database\Eloquent\Model;
use App\Models\Compliance\StandardControl;

class ComplianceStandardControlsMap extends Model
{
    protected $table ='compliance_standard_controls_maps';
    protected $fillable= ['control_id','linked_control_id'];

    public function control()
    {
        return $this->belongsTo(StandardControl::class, 'control_id');
    }

    public function linked_control(){
        return $this->belongsTo(StandardControl::class, 'linked_control_id');
    }

}
