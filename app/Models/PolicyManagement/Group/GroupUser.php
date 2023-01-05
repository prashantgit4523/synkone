<?php

namespace App\Models\PolicyManagement\Group;

use App\Casts\CustomCleanHtml;
use App\Models\DataScope\BaseModel;

class GroupUser extends BaseModel
{
    protected $table = 'policy_group_users';
    protected $fillable = ['first_name', 'last_name', 'email', 'department','user_type'];

    protected $casts = [
        'first_name' => CustomCleanHtml::class,
        'last_name' => CustomCleanHtml::class,
        'email' => CustomCleanHtml::class,
        'department' => CustomCleanHtml::class
    ];

    public function scopeOrderByName($query)
    {
        $query->orderBy('first_name')->orderBy('last_name');
    }
}
