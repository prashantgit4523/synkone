<?php

namespace App\Models\PolicyManagement\Group;

use App\Models\DataScope\BaseModel;
use App\Models\PolicyManagement\Campaign\Campaign;
use Database\Factories\GroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Casts\CustomCleanHtml;

class Group extends BaseModel
{
    use HasFactory;

    protected $table = 'policy_groups';
    protected $fillable = ['name', 'description', 'user_type'];

    protected $casts = [
        'name' => CustomCleanHtml::class
    ];

    /**
     * The users that belong to the group.
     */
    public function users()
    {

        return $this->hasMany(GroupUser::class, 'group_id');
    }

    /**
     * gets campaigns assigned this group.
     */
    public function campaigns()
    {
        return $this->belongsToMany(Campaign::class, 'policy_campaign_groups', 'group_id');
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
        return GroupFactory::new();
    }

    public function scopeOrderByName($query)
    {
        return $query->orderBy('name');
    }
}
