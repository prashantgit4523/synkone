<?php

namespace App\Models\Integration;

use App\Models\AssetManagement\Asset;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Staudenmeir\EloquentHasManyDeep\HasRelationships;

class IntegrationProvider extends Model
{
    use HasFactory;

    use HasRelationships;

    protected $fillable = ['accessToken', 'refreshToken', 'tokenExpires', 'previous_scopes_count', 'subscriptionId', 'required_fields', 'protocol'];

    protected $appends = ['current_scopes_count'];

    public function getCurrentScopesCountAttribute()
    {
        return config('services.' . $this->name . '.scopes') ? count(config('services.' . $this->name . '.scopes')) : 0;
    }

    public function assets()
    {
        return $this->hasMany(Asset::class);
    }

    public function integration()
    {
        return $this->hasOne(Integration::class, 'provider_id', 'id');
    }

    public function integration_controls()
    {
        return $this->hasManyDeepFromRelations($this->integration_actions(), (new IntegrationAction())->integration_controls());
    }
//
//    public function integration_actions()
//    {
//        return $this->belongsToMany(IntegrationAction::class);
//    }

    public function kpi_integration_controls()
    {
        return $this->hasManyDeepFromRelations($this->integration_actions(), (new IntegrationAction())->integration_controls())->withIntermediate(IntegrationAction::class, ['*'], 'action')->kpiEnabled();
    }

    public function integration_actions()
    {
        return $this->hasMany(IntegrationAction::class);
    }
}
