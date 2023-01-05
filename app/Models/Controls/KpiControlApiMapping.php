<?php

namespace App\Models\Controls;

use App\Helpers\SystemGeneratedDocsHelpers;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class KpiControlApiMapping extends Model
{
    use HasFactory;

    public function control()
    {
        return $this->belongsTo('App\Models\Compliance\StandardControl', 'control_id');
    }

    protected static function booted()
    {
        static::created(fn() => SystemGeneratedDocsHelpers::checkSystemGeneratedDocuments());
        static::deleted(fn() => SystemGeneratedDocsHelpers::checkSystemGeneratedDocuments());
    }
}
