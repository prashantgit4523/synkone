<?php

namespace App\Models\PolicyManagement;

use App\Casts\CustomCleanHtml;
use App\Models\DataScope\BaseModel;

class User extends BaseModel
{
    protected $table = 'policy_users';
    protected $fillable = ['email', 'first_name', 'last_name', 'department','status','user_type'];

    protected $casts = [
        'first_name' => CustomCleanHtml::class,
        'last_name' => CustomCleanHtml::class,
        'email' => CustomCleanHtml::class,
        'department' => CustomCleanHtml::class
    ];
}
