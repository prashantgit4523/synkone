<?php

namespace App\Http\Middleware;

use Closure;
use Carbon\Carbon;
use Inertia\Inertia;
use App\Nova\Model\Tenant;
use Illuminate\Http\Request;

class CheckTeanantStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $tenant=Tenant::where('id',tenant('id'))->first();
        $tenant_subs_expiration=$tenant->subscription_expiry_date;
        $nowDate = Carbon::now();
        
        if(is_null($tenant_subs_expiration)){
            abort(503,"Not Subscribed.");
        }

        if($nowDate->gt($tenant_subs_expiration)){
            return Inertia::render('errors/SubscriptionExpiredPage', ['expiry_date' =>$tenant_subs_expiration->toFormattedDateString()])
                    ->toResponse($request);
        }
        if($tenant->ready){
            return $next($request);
        }
        abort(503,'We are building your site. Please check after some time.');
    }
}
