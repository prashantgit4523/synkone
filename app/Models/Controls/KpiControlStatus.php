<?php

namespace App\Models\Controls;

use App\Helpers\SystemGeneratedDocsHelpers;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KpiControlStatus extends Model
{
    use HasFactory;

    protected $table = 'kpi_control_status';

    protected $fillable = ['control_id', 'total', 'per', 'passed', 'failed'];

    public function control()
    {
        return $this->belongsTo('App\Models\Compliance\StandardControl', 'control_id');
    }

    public function kpi_mapping()
    {
        return $this->belongsTo('App\Models\Controls\KpiControlApiMapping', 'control_id','control_id');
    }

    protected static function booted()
    {
        static::created(fn() => SystemGeneratedDocsHelpers::checkSystemGeneratedDocuments());
        static::deleted(fn() => SystemGeneratedDocsHelpers::checkSystemGeneratedDocuments());
    }
}
