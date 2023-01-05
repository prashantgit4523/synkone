<?php

namespace App\Models;

use App\Models\GlobalSettings\GlobalSetting;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportCategoryControl extends Model
{
    use HasFactory;

    protected $appends = ['formatted_description', 'formatted_alt_description'];

    protected $fillable = ['status', 'automation'];

    public function getFormattedDescriptionAttribute()
    {
        $globalSetting = GlobalSetting::first();
        
        return str_replace('Company X',$globalSetting->display_name,$this->description);
    }

    public function getFormattedAltDescriptionAttribute()
    {
        $globalSetting = GlobalSetting::first();

        return str_replace('Company X',$globalSetting->display_name,$this->alt_description);
    }
}
