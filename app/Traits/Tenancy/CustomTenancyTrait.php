<?php

namespace App\Traits\Tenancy;

use App\Nova\Model\Domain;
use App\Nova\Model\Tenant;

trait CustomTenancyTrait
{
    /**
     * setting db manually with http host
     */
    public static function set_db()
    {
        if(env('TENANCY_ENABLED') && isset($_SERVER['HTTP_HOST'])){
                $real_host=explode(':',$_SERVER['HTTP_HOST'])[0];
                $domain=Domain::where('domain',$real_host)->get();
                if($domain->count()>0){
                    $tenant=Tenant::where('id',$domain[0]->tenant_id)->first();
                    if($tenant){
                        \Config::set('database.connections.mysql.database', 'tenant'.$tenant->id);
                        \DB::purge('mysql');
                    }
                }
        }

    }

    /**
     * unsetting db manually 
     */
    public static function unset_db()
    {
        if(env('TENANCY_ENABLED')){
            \Config::set('database.connections.mysql.database', env('DB_DATABASE'));
            \DB::purge('mysql');
        }
    }
}