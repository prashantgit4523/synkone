<?php

namespace App\Http\Controllers\Auth;

use Carbon\Carbon;
use App\Nova\Model\Tenant;
use Illuminate\Http\Request;
use App\Utils\RegularFunctions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\UserManagement\Admin;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use App\CustomProviders\GoogleCloudIdentity;
use App\Models\UserManagement\AdminDepartment;
use App\Models\Integration\IntegrationProvider;
use App\Traits\Integration\IntegrationApiTrait;
use App\Models\Administration\OrganizationManagement\Organization;

class SocialiteLoginController extends Controller
{
    use IntegrationApiTrait;
    
    public function redirect($provider)
    {
        try {
            if($provider === 'microsoft'){
                $config = $this->getMicrosoftSSOConfig();  
            }
            
            if($provider === 'google'){
                $config = $this->getGoogleSSOConfig();  
            }
            
            if(env('TENANCY_ENABLED')){
                $redirectUrl = Socialite::driver($provider)->setConfig($config)->with(['state' => tenant('id')])->redirect()->getTargetUrl();
            }else{
                $redirectUrl = Socialite::driver($provider)->setConfig($config)->redirect()->getTargetUrl();
            } 
            
            return redirect($redirectUrl);
        } catch (\Exception $e) {
            Log::info("Failed to login",$e->getMessage());
        }
    }

    public function callback(Request $request, $provider)
    {
        $tenantUrl = $this->getDomainNameWithTenant($request->state);

        if(!$tenantUrl){
            return redirect()->route('homepage')->withError('Tenant not found.');
        }

        return redirect($tenantUrl . '/auth/sso/' . $provider . '/login?' . $request->getQueryString());
    }

    public function login(Request $request, $provider)
    {
        if (env('TENANCY_ENABLED') && $request->state !== tenant('id')) {
            return redirect()->route('integrations.index')->withError('Session url doesn\'t match with response url. Please try again later.');
        }

        if($provider == 'microsoft'){
            $config = $this->getMicrosoftSSOConfig();  
        }
        
        if($provider == 'google'){
            $config = $this->getGoogleSSOConfig();  
        }

        $user = Socialite::driver($provider)->setConfig($config)->stateless()->user();
        
        $searchUser = Admin::where(DB::raw('lower(email)'), strtolower($user->email))->first();
        
        if ($searchUser) {
            if ($searchUser->status !== 'disabled') {
                $searchUser->update([
                    'auth_method' => 'SSO',
                    'status' => 'active',
                    'is_sso_auth' => 1,
                    'last_login' => now()
                ]);

                Log::info("User login success.", [
                    'email' => $searchUser->email,
                ]);
                
                return $this->authenticated($searchUser);
            } else {
                return redirect()->route('sso-login')->withError('User with this email has been disabled.');
            }
        } else {
            if($provider === 'microsoft'){
                $userExists = $this->checkUserInAzure($user->email);

                if(!$userExists){
                    return redirect()->route('sso-login')->withError('User with this email cannot login in this tenant.');
                }

                $firstName = $user->givenName;
                $lastName = $user->surname ?? null;
            }

            if($provider === 'google'){
                $userExists = $this->checkUserInGoogleCloud($user->email);

                if(!$userExists){
                    return redirect()->route('sso-login')->withError('User with this email cannot login in this tenant.');
                }

                $firstName = $user->user['given_name'];
                $lastName = $user->user['family_name'];
            }
            
            $admin = Admin::create([
                'auth_method' => 'SSO',
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $user->email,
                'status' => 'active',
                'is_sso_auth' => 1,
                'is_manual_user' => 0,
                'status' => 'active',
                'last_login' => now()
            ]);

            $organization = Organization::first();

            /* Creating departments */
            $department = new AdminDepartment([
                'admin_id' => $admin->id,
                'organization_id' => $organization->id,
                'department_id' => null
            ]);

            $admin->department()->save($department);

            //assign contributor role
            DB::table('model_has_roles')->insert([
                'role_id' => 3,
                'model_type' => 'App\Models\UserManagement\Admin',
                'model_id' => $admin->id
            ]);

            Log::info("New user register success.", [
                'email' => $admin->email,
            ]);

            return $this->authenticated($admin);
        }
    }

    private function getMicrosoftSSOConfig()
    {
        return new \SocialiteProviders\Manager\Config(config('services.microsoft_sso.client_id'), config('services.microsoft_sso.client_secret'), config('services.microsoft_sso.redirect'), ['tenant' => 'common']);
    }

    private function getGoogleSSOConfig()
    {
        return new \SocialiteProviders\Manager\Config(config('services.google_sso.client_id'),config('services.google_sso.client_secret'), config('services.google_sso.redirect'));
    }

    /**
     * The user has been authenticated.
     *
     * @param mixed $user
     *
     * @return mixed
     */
    protected function authenticated($user)
    {
        $user->last_login = Carbon::now();
        $user->is_login = true;
        
        $user->update();

        Auth::guard('admin')->login($user);

        return redirect()->intended(RegularFunctions::getRoleBasedRedirectPath());
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

    private function checkUserInGoogleCloud($email){
        $googleCloudIdentity = new GoogleCloudIdentity();
        $user = $googleCloudIdentity->findUser($email);
        
        if (count($user) && isset($user['primaryEmail']) && $user['suspended'] === false && $user['archived'] === false) {
            return true;
        }
        return false;
    }

    private function checkUserInAzure($email){
        $provider = IntegrationProvider::where('name','office-365')->first();
        
        $this->checkTokenExpiration('https://login.microsoftonline.com/common/oauth2/v2.0/token',$provider);

        $response = Http::withToken($provider->accessToken)
                ->get('https://graph.microsoft.com/v1.0/users/'.$email);
        
        if ($response->ok()) {
            $body = json_decode($response->body(), true);

            if(count($body) && isset($body['mail'])){
                return true;
            }
            return false;
        }
        return false;
    }

    private function checkTokenExpiration($tokenUrl,$provider)
    {
        if (!empty($provider->refreshToken) && !empty($provider->accessToken) && $this->validateToken($provider->tokenExpires)) {
            //checks for token expiration & refresh the token
            $this->refreshExpiredToken($provider->refreshToken, $provider, $tokenUrl);
        }
    }
}
