<?php

namespace App\Models\Integration;

use Illuminate\Database\Eloquent\Model;

class Integration extends Model
{
    protected $table = 'integrations';

    protected $with = ['category'];

    protected $appends = ['logo_link','implemented_integration', 'ready', 'one_per_category'];

    protected $fillable = ['connected'];

    const IMPLEMENTED_INTEGRATIONS = [2,3,5,7,8,9,11,12,16,17,18,21,22,32,35,36,38,39,40,42,43,44,45,46,47,48]; // 14,

    const ONLY_ONE_INTEGRATION_ENABLED_CATEGORY = [2,3,4,6,7];

    public function getLogoLinkAttribute()
    {
        return asset('assets/images/integrations/'.$this->logo);
    }

    public function provider()
    {
        return $this->belongsTo(IntegrationProvider::class);
    }

    public function getReadyAttribute(){
        return in_array($this->id, self::IMPLEMENTED_INTEGRATIONS);
    }

    public function getOnePerCategoryAttribute() {
        return in_array($this->category_id, self::ONLY_ONE_INTEGRATION_ENABLED_CATEGORY);
    }

    public function getImplementedIntegrationAttribute()
    {
        if(in_array($this->category_id, self::ONLY_ONE_INTEGRATION_ENABLED_CATEGORY)){
            $connected_integration = $this->where('category_id',$this->category_id)->where('connected',1)->first();

            return $connected_integration ? $this->id === $connected_integration->id : in_array($this->id, self::IMPLEMENTED_INTEGRATIONS);
        }

        return in_array($this->id, self::IMPLEMENTED_INTEGRATIONS);
    }

    public function category()
    {
        return $this->belongsTo(IntegrationCategory::class, 'category_id', 'id');
    }
}
