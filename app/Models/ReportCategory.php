<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportCategory extends Model
{
    use HasFactory;

    protected $appends = ['controls_count', 'controls_status'];

    public function controls(){
        return $this->hasMany(ReportCategoryControl::class,'report_category_id');
    }

    public function getControlsCountAttribute(){
        return $this->controls()->count();
    }

    public function getControlsStatusAttribute(){
        return !$this->controls()->where('status',0)->count() > 0;
    }
}
