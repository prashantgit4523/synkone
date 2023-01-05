<?php

namespace App\Http\Controllers\Integration\Auth;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Laravel\Socialite\Facades\Socialite;
use App\Traits\Integration\TokenValidateTrait;
use App\Models\Integration\IntegrationProvider;
use App\Nova\Model\Tenant;
use App\Traits\Integration\IntegrationApiTrait;

class IntegrationAuthController extends Controller
{
    use IntegrationApiTrait, TokenValidateTrait;

    public function loginUrl($slug)
    {
        $provider = $this->getProviderByIntegrationSlug($slug);

        if(!$provider){
            return redirect()->route('integrations.index')->withError('Provider not found.');
        }

        $scopes = config('services.' . $provider->name . '.scopes');

        session([
            'integration_service_name_'.tenant('id') => $slug
        ]);

        sleep(2);

        $parameters = ['access_type' => 'offline', "prompt" => "consent"];
        
        if(env('TENANCY_ENABLED')){
            $parameters['state'] = tenant('id');
        }
        
        $redirectUrl = Socialite::driver($provider->driver)->scopes($scopes)->with($parameters)->redirect()->getTargetUrl();
        
        return redirect($redirectUrl);
    }

    public function loginCallback(Request $request)
    {
        $tenantUrl = $this->getDomainNameWithTenant($request->state);

        if(!$tenantUrl){
            return redirect()->route('homepage')->withError('Tenant not found.');
        }

        if($request->error && in_array($request->error,['access_denied','consent_required'])){
            return redirect($tenantUrl."/integrations/Error?code=cancel");
        }
        
        if (!$request->has('code') || !$request->has('state')) {
            return redirect($tenantUrl."/integrations/Error?code=invalidUrl");
        }

        $redirectUrl = $tenantUrl . '/integrate/service?' . $request->getQueryString();

        return redirect($redirectUrl);
    }

    public function integrateService(Request $request)
    {
        if (env('TENANCY_ENABLED') && $request->state !== tenant('id')) {
            return redirect()->route('integrations.index')->withError('Session url doesn\'t match with response url. Please try again later.');
        }

        $provider = $this->getProviderByIntegrationSlug(session('integration_service_name_'.tenant('id')));

        if(!$provider){
            return redirect()->route('integrations.index')->withError('Provider not found.');
        }
        
        $user = Socialite::driver($provider->driver)->stateless()->user();
        
        if(isset($user->error)){
            return redirect()->route('integrations.index')->withError($user->error);
        }

        $this->storeToken($user, $provider->name);

        $serviceName = $this->connectIntegration(session('integration_service_name_'.tenant('id')));

        if (!$serviceName) {
            return redirect()->route('integrations.index')->withError('Failed to connect to service.');
        }

        //update scopes count
        $provider = IntegrationProvider::where('name', $provider->name)->first();
        $provider->update([
            'previous_scopes_count' => config('services.' . $provider->name . '.scopes') ? count(config('services.' . $provider->name . '.scopes')) : 0
        ]);

        callArtisanCommand('assets:fetch');

        return redirect()->route('integrations.index')->withSuccess("{$serviceName} integrated successfully.");
    }

    private function getDomainNameWithTenant($tenantId)
    {
        if(env('TENANCY_ENABLED')){
            $tenant = Tenant::where('id', $tenantId)->first();

            if(!$tenant){
                return false;
            }

            $domain = $tenant->domains()->first();

            if(!$domain){
                return false;
            }

            $protocol = request()->secure() ? 'https://' : 'http://';

            return $protocol . $domain->domain;
        }
        else{
            return request()->getSchemeAndHttpHost();
        }
    }
}
