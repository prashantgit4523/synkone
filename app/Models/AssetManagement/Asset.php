<?php

namespace App\Models\AssetManagement;

use App\Models\Integration\Integration;
use App\Models\Integration\IntegrationProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Asset extends Model
{
    use HasFactory;

    protected $table = 'assets';
    protected $fillable = ['name', 'description', 'type', 'owner', 'classification'];

    public function provider()
    {
        return $this->belongsTo(IntegrationProvider::class, 'integration_provider_id', 'id');
    }

    public function integration()
    {
        return $this->belongsTo(Integration::class, 'integration_provider_id', 'provider_id');
    }
}
