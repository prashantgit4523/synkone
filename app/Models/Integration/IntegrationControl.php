<?php

namespace App\Models\Integration;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Staudenmeir\EloquentHasManyDeep\HasRelationships;

class IntegrationControl extends Model
{
    use HasFactory;
    use HasRelationships;

    protected $fillable = ['standard_id', 'primary_id', 'sub_id', 'kpi_enabled', 'kpi_description'];

    public function integration_actions()
    {
        return $this->belongsToMany(IntegrationAction::class)->withPivot(['is_compliant', 'last_response']);
    }

    public function integration_providers()
    {
        return $this->hasManyDeepFromRelations($this->integration_actions(), (new IntegrationAction())->integration_provider());
    }

    public function integrations()
    {
        return $this->hasManyDeepFromRelations($this->integration_providers(), (new IntegrationProvider())->integration())
            ->withPivot('integration_action_integration_control', ['is_compliant', 'last_response', 'how_to_implement'])
            ->withIntermediate(IntegrationAction::class);
    }

//

//
//    public function action()
//    {
//        return $this->belongsTo(IntegrationAction::class, 'integration_action_id', 'id');
//    }

    public function scopeKpiEnabled($query)
    {
        return $query->where('kpi_enabled', 1);
    }
    
    public function integration_provider()
    {
        return $this->belongsTo(IntegrationProvider::class, 'last_implemented_by', 'id');
    }
}
