<?php

namespace App\Models\RiskManagement;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RisksTemplate extends Model
{
    use HasFactory;
    
    protected $table = 'risks_template';
    protected $fillable = [
        'primary_id',
        'sub_id',
        'standard_id',
        'category_id',
        'name',
        'risk_description',
        'affected_properties',
        'treatment',
    ];

    public function category()
    {
        return $this->belongsTo('App\Models\RiskManagement\RiskCategory', 'category_id');
    }

    public function standard()
    {
        return $this->belongsTo('App\Models\RiskManagement\RiskStandard', 'standard_id');
    }
}
