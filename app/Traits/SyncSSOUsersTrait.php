<?php

namespace App\Traits;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\PolicyManagement\User;
use App\Models\PolicyManagement\PolicySystemUser;
use App\Models\Integration\Integration;
use App\CustomProviders\GoogleCloudIdentity;
use App\Models\PolicyManagement\Group\Group;
use App\Models\Integration\IntegrationCategory;
use App\Traits\Integration\IntegrationApiTrait;

trait SyncSSOUsersTrait
{
    use IntegrationApiTrait;

    private $office_365_slug = 'office-365';
    private $google_cloud_identity = 'google-cloud-identity';

    public function fetchSSOUsersAndGroups()
    {
        try {
            $connectedIntegration = Integration::where('category_id', IntegrationCategory::SSO_ID)->where('connected', 1)->first();
            
            if ($connectedIntegration->slug === $this->office_365_slug) {
                $this->checkTokenExpiration('https://login.microsoftonline.com/common/oauth2/v2.0/token', $connectedIntegration->provider);

                $response = Http::withToken($connectedIntegration->provider->accessToken)
                    ->get('https://graph.microsoft.com/v1.0/users?$select=givenname,surname,mail,department,accountEnabled&$filter=accountEnabled eq true');

                if ($response->ok()) {
                    $body = json_decode($response->body(), true);

                    $users = $body['value'] ?? $body;

                    if(count($users)){
                        return $this->createSSOGroupAndUsers($users, $this->office_365_slug);
                    }

                    return redirect()->back()->withError('SSO users not found.');
                }
                return redirect()->back()->withError('Failed to fetch users.');
            }

            if ($connectedIntegration->slug === $this->google_cloud_identity) {
                $googleCloudIdentity = new GoogleCloudIdentity();
                $users = $googleCloudIdentity->getUsersLists();
                
                if (count($users)) {
                    return $this->createSSOGroupAndUsers($users, $this->google_cloud_identity);
                }

                return redirect()->back()->withError('SSO users not found.');
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return redirect()->back()->withError('Failed to fetch users.');
        }
    }

    public function fetchSystemUsers():void
    {
        try {
            $connectedIntegration = Integration::where('category_id', IntegrationCategory::SSO_ID)->where('connected', 1)->first();
            
            if ($connectedIntegration && $connectedIntegration->slug === $this->office_365_slug) {
                $this->checkTokenExpiration('https://login.microsoftonline.com/common/oauth2/v2.0/token', $connectedIntegration->provider);

                $response = Http::withToken($connectedIntegration->provider->accessToken)
                    ->get('https://graph.microsoft.com/v1.0/users?$select=givenname,surname,mail,department,accountEnabled&$filter=accountEnabled eq true');

                if ($response->ok()) {
                    $body = json_decode($response->body(), true);

                    $users = $body['value'] ?? $body;

                    if(count($users)){
                        $this->createSystemUsers($users, $this->office_365_slug);
                    }
                }
            }elseif ($connectedIntegration && $connectedIntegration->slug === $this->google_cloud_identity) {
                $googleCloudIdentity = new GoogleCloudIdentity();
                $users = $googleCloudIdentity->getUsersLists();
                
                if (count($users)) {
                    $this->createSystemUsers($users, $this->google_cloud_identity);
                }

            }else{
                $this->createSystemUsers();
            }
        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
    }


    private function getConnectedIntegrationWithSlug($slug)
    {
        $integration = Integration::where('slug', $slug)->where('connected', 1)->first();

        return $integration ? true : false;
    }

    private function checkTokenExpiration($tokenUrl, $provider)
    {
        if (!empty($provider->refreshToken) && !empty($provider->accessToken) && $this->validateToken($provider->tokenExpires)) {
            //checks for token expiration & refresh the token
            $this->refreshExpiredToken($provider->refreshToken, $provider, $tokenUrl);
            $provider->refresh();
        }
    }

    private function createSSOGroupAndUsers($users, $integration){
        $groupCreated = DB::transaction(function () use ($users, $integration) {
            $groupCreated = Group::firstOrCreate(
                ['name' => 'All SSO users'],
                [
                    'name' => 'All SSO users',
                    'user_type' => 'SSO'
                ]
            );

            ///Add all group users
            foreach ($users as $user) {
                if($integration === $this->office_365_slug){
                    $user_email = $user['mail'];
                    $user_department = $user['department'] ?? null;
                    $user_first_name = $user['givenName'];
                    $user_last_name = $user['surname'] ?? null;
                }

                if($integration === $this->google_cloud_identity){
                    $user_email = $user['primaryEmail'];
                    $user_department = isset($user['organizations'][0]['department']) ? $user['organizations'][0]['department'] : null;
                    $user_first_name = $user['name']['givenName'];
                    $user_last_name = $user['name']['familyName'] ?? null;
                }

                $ssoUser['user_type'] = 'SSO';
                $ssoUser['status'] = 'active';
                $ssoUser['email'] = $user_email;
                $ssoUser['department'] = $user_department;
                $ssoUser['first_name'] = $user_first_name;
                $ssoUser['last_name'] = $user_last_name;

                if ($ssoUser['first_name'] && $ssoUser['email']) {
                    /*Creating user in users template section*/
                    User::updateOrCreate(
                        [
                            'email' => $ssoUser['email']
                        ],
                        $ssoUser
                    );

                    /* Creating Group Users */
                    $groupCreated->users()->updateOrCreate(
                        [
                            'email' => $ssoUser['email']
                        ],
                        $ssoUser
                    );

                    //create group with department name & assign users
                    if ($ssoUser['department']) {
                        $department = Group::firstOrCreate(
                            ['name' => $ssoUser['department']],
                            [
                                'name' => $ssoUser['department'],
                                'user_type' => 'SSO'
                            ]
                        );

                        $department->users()->updateOrCreate(['email' => $ssoUser['email']], $ssoUser);
                    }
                }
            }
            return $groupCreated;
        });

        if (!$groupCreated) {
            return redirect()->back()->withError('Failed to add sso users.');
        }

        return redirect()->back()->withSuccess('SSO users synced successfully.');
    }

    private function createSystemUsers($users = null, $integration = null):void
    {
        DB::transaction(function () use ($users, $integration) {
            DB::table('policy_system_users')->truncate();

            $manualUsers = User::where('user_type','manual')->get();
            foreach ($manualUsers as $manualUser) {
                $manualUserArr['user_type'] =  $manualUser->user_type;
                $manualUserArr['status'] =  $manualUser->status;
                $manualUserArr['email'] = $manualUser->email;
                $manualUserArr['department'] = $manualUser->department;
                $manualUserArr['first_name'] = $manualUser->first_name;
                $manualUserArr['last_name'] = $manualUser->last_name;
                if ($manualUserArr['first_name'] && $manualUserArr['email']) {
                    /*Creating user in users template section*/
                    PolicySystemUser::updateOrCreate(
                        [
                            'email' => $manualUserArr['email']
                        ],
                        $manualUserArr
                    );
                }
            }

            ///Add all group users
            if($users){
                foreach ($users as $user) {
                    if($integration === $this->office_365_slug){
                        $user_email = $user['mail'];
                        $user_department = $user['department'] ?? null;
                        $user_first_name = $user['givenName'];
                        $user_last_name = $user['surname'] ?? null;
                    }
    
                    if($integration === $this->google_cloud_identity){
                        $user_email = $user['primaryEmail'];
                        $user_department = isset($user['organizations'][0]['department']) ? $user['organizations'][0]['department'] : null;
                        $user_first_name = $user['name']['givenName'];
                        $user_last_name = $user['name']['familyName'] ?? null;
                    }
    
                    $ssoUser['user_type'] = 'SSO';
                    $ssoUser['status'] = 'active';
                    $ssoUser['email'] = $user_email;
                    $ssoUser['department'] = $user_department;
                    $ssoUser['first_name'] = $user_first_name;
                    $ssoUser['last_name'] = $user_last_name;
    
                    if ($ssoUser['first_name'] && $ssoUser['email']) {
                        /*Creating user in users template section*/
                        PolicySystemUser::updateOrCreate(
                            [
                                'email' => $ssoUser['email']
                            ],
                            $ssoUser
                        );
                    }
                }
            }

        });
    }
}
