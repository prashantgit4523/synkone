<?php

namespace App\Models\RiskManagement;

use App\Casts\CustomCleanHtml;
use App\Models\DataScope\Scopable;
use App\Models\DataScope\SysDocBaseModel;
use Database\Factories\RiskManagement\ProjectFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Project extends SysDocBaseModel
{
    use SoftDeletes, HasFactory;

    protected $table = 'risk_projects';
    protected $fillable = ['owner_id', 'name', 'description'];
    protected $appends = ['department_title'];

    protected $casts = [
        'name' => CustomCleanHtml::class,
        'description' => CustomCleanHtml::class,
    ];

    /**
     * Method department
     *
     * @return void
     */
    public function department()
    {
        return $this->morphOne(Scopable::class, 'scopable');
    }

    public function of_standard()
    {
        return $this->belongsTo(Standard::class, 'standard_id');
    }

    public function risk_registers()
    {
        return $this->hasMany(RiskRegister::class, 'project_id');
    }
    /**
     * Get project owner.
     */
    public function owner()
    {
        return $this->belongsTo('App\Models\UserManagement\Admin', 'owner_id');
    }

    protected static function newFactory()
    {
        return ProjectFactory::new();
    }
}
