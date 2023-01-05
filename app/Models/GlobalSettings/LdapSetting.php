<?php

namespace App\Models\GlobalSettings;

use App\Casts\CustomCleanHtml;
use Illuminate\Database\Eloquent\Model;

class LdapSetting extends Model
{
    protected $table = 'ldap_settings';

    protected $fillable = [
        'hosts',
        'base_dn',
        'username',
        'password',
        'port',
        'use_ssl',
        'version',
        'map_first_name_to',
        'map_last_name_to',
        'map_email_to',
        'map_contact_number_to'
    ];

    protected $casts = [
        'hosts' => CustomCleanHtml::class,
        'username' => CustomCleanHtml::class,
        'port' => CustomCleanHtml::class,
        'version' => CustomCleanHtml::class,
        'base_dn' => CustomCleanHtml::class,
        'map_first_name_to' => CustomCleanHtml::class,
        'map_email_to' => CustomCleanHtml::class,
        'map_last_name_to' => CustomCleanHtml::class,
        'map_contact_number_to' => CustomCleanHtml::class
    ];
}
