<?php


namespace App\Models\RiskManagement;


use Illuminate\Database\Eloquent\Model;

class RiskStandard extends Model
{
    protected $table = 'risks_standards';

    protected $fillable = [
        'logo',
        'description'
    ];

    protected $guarded = ['id'];

    public function risks()
    {
        return $this->hasMany('App\Models\RiskManagement\RisksTemplate', 'standard_id');
    }

    public function getLogoAttribute($value)
    {
        return asset('assets/images/standards/' . $value);
    }
}
