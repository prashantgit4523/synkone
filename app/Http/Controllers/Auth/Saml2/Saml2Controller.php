<?php

namespace App\Http\Controllers\Auth\Saml2;

use Illuminate\Http\Request;
use App\Saml2Sp\Saml2Auth;
use App\Models\Administration\OrganizationManagement\Organization;
use App\Models\UserManagement\Admin;
use App\Events\Auth\Saml2\Saml2LoginEvent;
use App\Models\UserManagement\AdminDepartment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;


class Saml2Controller extends Controller
{

    public function getMetadata(Saml2Auth $saml2Auth)
    {
        $metadata = $saml2Auth->getMetadata();

        return response($metadata, 200, ['Content-Type' => 'text/xml']);
    }

    /**
     * Initiate a login request.
     *
     * @param Saml2Auth $saml2Auth
     */
    public function login(Saml2Auth $saml2Auth)
    {
        $saml2Auth->login(config('saml2_settings.loginRoute'));
    }

    /**
     * Process an incoming saml2 assertion request.
     * Fires 'Saml2LoginEvent' event if a valid user is found.
     *
     * @param Saml2Auth $saml2Auth
     * @param $idpName
     * @return \Illuminate\Http\Response
     */
    public function acs(Saml2Auth $saml2Auth)
    {
        $errors = $saml2Auth->acs();

        if (!empty($errors)) {
            logger()->error('Saml2 error_detail', ['error' => $saml2Auth->getLastErrorReason()]);
            session()->flash('saml2_error_detail', [$saml2Auth->getLastErrorReason()]);

            logger()->error('Saml2 error', $errors);
            return redirect(route('sso-login'))->withError($saml2Auth->getLastErrorReason());
        }
        $idpUser = $saml2Auth->getSaml2User();

        $creatingSSO = $this->createSSOUser($idpUser);

        if (isset($creatingSSO['error'])) {
            return redirect(route('sso-login'))->withError($creatingSSO['message']);
        }
        // Setting login status to true for auto-logout when user department change
        $user = Admin::where('email',$idpUser->getNameId())->firstOrFail();
        $user->update(['is_login' => 1]);

        // Authentication to ebdaa app
        event(new Saml2LoginEvent($idpUser, $saml2Auth));

        $redirectUrl = $idpUser->getIntendedUrl();

        return ($redirectUrl !== null) ? redirect($redirectUrl) : redirect(config('saml2_settings.loginRoute'));
    }

    /**
     * Initiate a logout request across all the SSO infrastructure.
     *
     * @param Saml2Auth $saml2Auth
     * @param Request $request
     */
    public function logout(Saml2Auth $saml2Auth, Request $request)
    {
        $returnTo = $request->query('returnTo');
        $sessionIndex = $request->query('sessionIndex');
        $nameId = $request->query('nameId');
        $saml2Auth->logout($returnTo, $nameId, $sessionIndex); //will actually end up in the sls endpoint
        //does not return
    }


    /**
     * Process an incoming saml2 logout request.
     * Fires 'Saml2LogoutEvent' event if its valid.
     * This means the user logged out of the SSO infrastructure, you 'should' log them out locally too.
     *
     * @param Saml2Auth $saml2Auth
     * @param $idpName
     * @return \Illuminate\Http\Response
     */
    public function sls(Saml2Auth $saml2Auth)
    {
        $errors = $saml2Auth->sls(config('saml2_settings.retrieveParametersFromServer'));
        if (!empty($errors)) {
            logger()->error('Saml2 error', $errors);
            session()->flash('saml2_error', $errors);
            throw new \Exception("Could not log out");
        }

        return redirect(config('saml2_settings.logoutRoute')); //may be set a configurable default
    }

    public function createSSOUser($idpUser)
    {
        if (!$idpUser) {
            return [
                'error' => 1,
                'message' => 'Email-address missing for IDP user.'
            ];
        }
        $searchUser = Admin::where(DB::raw('lower(email)'), strtolower($idpUser->getNameId()))->first();

        if ($searchUser) {
            return $this->checkUser($searchUser);
        } 
        else {
            $keys = array_keys($idpUser->getAttributes());
            foreach ($keys as $key) {
                if (Str::afterLast($key, '/') == 'givenname') {
                    $firstName = $idpUser->getAttributes()[$key][0];
                }
                if (Str::afterLast($key, '/') == 'surname') {
                    $lastName = $idpUser->getAttributes()[$key][0];
                }
            }
            if (!isset($firstName) || !isset($lastName)) {
                return [
                    'error' => 1,
                    'message' => 'Missing first or last name for IDP user.'
                ];
            }
            $admin = Admin::create([
                'auth_method' => 'SSO',
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $idpUser->getNameId(),
                'status' => 'active',
                'is_sso_auth' => 1,
                'is_manual_user' => 0,
                'last_login' => now(),
                'is_login' => 1
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

            return true;
        }
    }

    public function checkUser($searchUser){
        if ($searchUser->status !== 'disabled') {
            $searchUser->update([
                'auth_method' => 'SSO',
                'status' => 'active',
                'is_sso_auth' => 1,
                'last_login' => now(),
                'is_login' => 1
            ]);

            Log::info("User login success.", [
                'email' => $searchUser->email,
            ]);

            return true;
        } else {
            return [
                'error' => 1,
                'message' => 'User with this email has been disabled.'
            ];
        }
    }
}
