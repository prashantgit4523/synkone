<?php

namespace App\Http\Middleware;

use App\Helpers\DataScope\DataScopeHelpers;
use Inertia\Middleware;
use App\Nova\Model\Tenant;
use Illuminate\Http\Request;
use App\Models\GlobalSettings\GlobalSetting;
use App\Models\Administration\OrganizationManagement\Organization;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     * @var string
     */
    protected $rootView = 'app-inertia';

    // public function rootView(Request $request)
    // {
    //     if (auth()->guard('admin')->check()) {
    //         return 'app-inertia';
    //     } else {
    //         return 'auth-inertia';
    //     }
    // }

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     * @param  \Illuminate\Http\Request  $request
     * @return string|null
     */
    public function version(Request $request)
    {
        return parent::version($request);
    }

    /**
     * Defines the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function share(Request $request)
    {
        $data_scope = DataScopeHelpers::boot();

        $flashData = [
            'message' => fn () => $request->session()->get('message'),
            'success' => fn () => $request->session()->get('success'),
            'error_message' => fn () => $request->session()->get('error_message'),
            'error' => fn () => $request->session()->get('error'),
            'status' => fn () => $request->session()->get('status'),
            'warning' => fn () => $request->session()->get('warning'),
            'info' => fn () => $request->session()->get('info'),
            'exception' => fn () => $request->session()->get('exception'),
            'csv_upload_error' => fn () => $request->session()->get('csv_upload_error'),
            'domain' => fn () => $request->session()->get('domain')
        ];

        /* data */
        if ($request->session()->has('data')) {
            $flashData['data'] = fn () => $request->session()->get('data');
        }

        if(tenant()){
            $tenant=Tenant::where('id',tenant()->id)->first();
            $subs_expiry_date=$tenant->subscription_expiry_date;
            if(!is_null($subs_expiry_date)){
                $subs_expiry_date=$subs_expiry_date->toFormattedDateString();
            }
        }
        else{
            $subs_expiry_date=null;
        }

        /** intercom messanger  */
        $intercom_hash=null;
        $intercom_user_id=null;
        if(auth()->check()){
            // just creating an unique id for the user. 
            $intercom_user_id=strtotime(auth()->user()->created_at) . auth()->user()->first_name;
            $intercom_hash=hash_hmac(
                    'sha256', // hash function
                    $intercom_user_id, // user's id
                    env('INTERCOM_SECRET_KEY') // secret key (keep safe!)
                );
        }
        return array_merge(parent::share($request), [
            'globalSetting' => GlobalSetting::first(),
            'organization'=> Organization::first()?Organization::first():'CyberArrow',
            'intercom_hash'=>$intercom_hash,
            'intercom_app_id'=>env('INTERCOM_APP_ID'),
            'intercom_user_id'=> $intercom_user_id,
            'isAuth' => fn () => auth()->check(),
            'authUser' => fn () => auth()->user()
                ? auth()->user()->only('id', 'avatar', 'first_name', 'last_name', 'full_name','email','department_name')
                : null,
            'authUserRoles' => fn () => auth()->user()
                ? auth()->user()->roles()->pluck('name')
                : null,
            'isSSOConfigured' => false,
            'flash' => $flashData,
            'activeTab' => function () use ($request) {
                return $request->session()->get('activeTab');
            },
            'current_page' => function () use ($request) {
                return $request->session()->get('current_page');
            },
            'tenancy_enabled'=>env('TENANCY_ENABLED'),
            'license_enabled'=>env('LICENSE_ENABLED'),
            'file_driver'=>config('filesystems.default'),
            'subscription_expiry'=>date('j M, Y', strtotime($subs_expiry_date)),
            'request_url'=>$request->url(),
            'previous_url'=>url()->previous(),
            'data_scope' => $data_scope
        ]);
    }
}
