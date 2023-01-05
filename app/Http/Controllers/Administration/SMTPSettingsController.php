<?php

namespace App\Http\Controllers\Administration;

use Validator;
use App\Nova\Model\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Laravel\Socialite\Facades\Socialite;
use App\Models\GlobalSettings\MailSetting;
use App\Models\GlobalSettings\SmtpProvider;
use App\Models\GlobalSettings\GlobalSetting;
use App\Models\GlobalSettings\SmtpProviderAlias;
use App\CustomProviders\Mail\GoogleMailTransport;
use App\CustomProviders\Mail\Office365MailTransport;

class SMTPSettingsController extends Controller
{
    public function redirectUrl($slug)
    {
        $connectedProvider = SmtpProvider::where('connected', 1)->first();
        $provider = SmtpProvider::where('slug', $slug)->first();

        if (!$provider) {
            return redirect()->route('global-settings')->with([
                'error' => 'Couldn\'t find SMTP provider.',
                'activeTab' => 'smtpSettings',
            ]);
        }

        if ($connectedProvider) {
            return redirect()->route('global-settings')->with([
                'error' => $connectedProvider->provider_name . ' is already connected. Please disconnect it first and try again.',
                'activeTab' => 'smtpSettings',
            ]);
        }

        $scopes = config('services.' . $slug . '-smtp' . '.scopes');

        session([
            'integration_service_name_' . tenant('id') => $slug
        ]);

        sleep(2);

        $parameters = ['access_type' => 'offline', "prompt" => "consent"];

        if (env('TENANCY_ENABLED')) {
            $parameters['state'] = tenant('id');
        }

        $config = $this->getProviderConfig($slug);

        $redirectUrl = Socialite::driver($provider->driver)
                        ->setConfig($config)
                        ->scopes($scopes)
                        ->with($parameters)
                        ->redirect()
                        ->getTargetUrl();

        return redirect($redirectUrl);
    }

    public function loginCallback(Request $request)
    {
        $tenantUrl = $this->getDomainNameWithTenant($request->state);

        if (!$tenantUrl) {
            return redirect()->route('homepage')->with([
                'error' => 'Tenant not found.',
                'activeTab' => 'smtpSettings',
            ]);
        }

        if ($request->error && in_array($request->error, ['access_denied', 'consent_required'])) {
            return redirect($tenantUrl . "/integrations/Error?code=cancel");
        }

        if (!$request->has('code') || !$request->has('state')) {
            return redirect($tenantUrl . "/integrations/Error?code=invalidUrl");
        }

        $redirectUrl = $tenantUrl . '/integrate/smtp-service?' . $request->getQueryString();

        return redirect($redirectUrl);
    }

    public function integrateService(Request $request)
    {
        DB::beginTransaction();

        if (env('TENANCY_ENABLED') && $request->state !== tenant('id')) {
            return redirect()->route('global-settings')->with([
                'error' => 'Session url doesn\'t match with response url. Please try again later.',
                'activeTab' => 'smtpSettings',
            ]);
        }

        $provider = SmtpProvider::where('slug', session('integration_service_name_' . tenant('id')))->first();

        if (!$provider) {
            return redirect()->route('global-settings')->with([
                'error' => 'SMTP provider not found.',
                'activeTab' => 'smtpSettings',
            ]);
        }

        $config = $this->getProviderConfig($provider->slug);

        $user = Socialite::driver($provider->driver)->setConfig($config)->stateless()->user();

        if (isset($user->error)) {
            return redirect()->route('global-settings')->with([
                'error' => $user->error,
                'activeTab' => 'smtpSettings',
            ]);
        }

        $tokenStored = $this->storeToken($user, $provider->slug);
        $provider->refresh();

        if (!$tokenStored) {
            DB::rollBack();

            return redirect()->route('global-settings')->with([
                'error' => 'Failed to connect to service.',
                'activeTab' => 'smtpSettings',
            ]);
        } else {
            //Fetch Aliases
            if ($provider->slug === 'gmail') {
                $gmail = new GoogleMailTransport($provider);

                $fetchedStatus = $gmail->fetchGmailAliases();

                if (!$fetchedStatus) {
                    DB::rollBack();

                    return redirect()->route('global-settings')->with([
                        'error' => 'Failed to fetch aliases. Please check all checkboxes on consent screen.',
                        'activeTab' => 'smtpSettings',
                    ]);
                }
            }
        }

        $this->resetManualMailSettings();

        DB::commit();

        return redirect()->route('global-settings')->with([
            'success' => "SMTP setup completed successfully.",
            'activeTab' => 'smtpSettings',
        ]);
    }

    public function disconnect(Request $request)
    {
        $provider = SmtpProvider::where('slug', $request->slug)->first();

        $data = [
            'access_token' => null,
            'refresh_token' => null,
            'token_expires' => null,
            'connected' => 0,
            'from_address' => null,
            'from_name' => null
        ];

        $provider->update($data);

        //remove aliases
        SmtpProviderAlias::where('smtp_provider_id', $provider->id)->delete();

        return redirect()->route('global-settings')->with([
            'success' => "SMTP provider disconnected successfully.",
            'activeTab' => 'smtpSettings',
        ]);
    }

    public function update(Request $request)
    {
        $validator = Validator::make($request->all(),
            [
                'from_address' => 'required|email'
            ],
            [
                'from_address.required' => 'The sender address field is required.',
                'from_address.email' => 'The sender address must be a valid email address.'
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->messages()
            ]);
        }

        try {
            $provider = SmtpProvider::where('connected', 1)->first();
            $selectedAlias = SmtpProviderAlias::where('selected', 1)->first();

            if (!$provider) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Couldn\'t find smtp provider.'
                ]);
            }

            $this->sendTestMail($provider, $request->from_address);

            if ($provider->slug === 'gmail' && $selectedAlias->email !== $request->from_address) {
                SmtpProviderAlias::where('email', $request->from_address)->update(['selected' => 1]);
                SmtpProviderAlias::where('email', '!=', $request->from_address)->update(['selected' => 0]);
            }

            if ($provider->slug === 'office-365') {
                $provider->update(['from_address' => $request->from_address]);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Mail settings updated successfully.'
            ]);
        }catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'ErrorSendAsDenied')) {
                $errorMsg = 'The configured email account is not authorized to send emails on behalf of '.$request->from_address.', use another Sender Address.';
            }else {
                $errorMsg = 'Failed to process request. Please check '.$provider->provider_name.' authentication connection.';
            }

            return response()->json([
                'status' => 'error',
                'message' => $errorMsg
            ]);
        }
    }

    public function testSmtpOauthMailConnection()
    {
        \Log::info('User is testing oauth mail connection');

        try {
            $provider = SmtpProvider::where('connected', 1)->first();

            if (!$provider) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Couldn\'t find smtp provider.'
                ]);
            }

            $this->sendTestMail($provider, $provider->from_address);

            Log::info('User '.$provider->provider_name.' mail connection successs');

            return response()->json([
                'status' => 'success',
                'message' => 'Connection to '.$provider->provider_name.' established successfully.',
            ]);
        } catch (\Exception $exception) {
            Log::info('User '.$provider->provider_name.' mail connection failed');

            if (str_contains($exception->getMessage(), 'ErrorSendAsDenied')) {
                $errorMsg = 'The configured email account is not authorized to send emails on behalf of '.$provider->from_address.', use another Sender Address.';
            }else {
                $errorMsg = 'Failed to process request. Please check '.$provider->provider_name.' authentication connection.';
            }

            return response()->json([
                'status' => 'error',
                'message' => $errorMsg
            ]);
        }
    }

    public function refreshAliases()
    {
        $provider = SmtpProvider::where('connected', 1)->first();

        $transport = (new GoogleMailTransport($provider));

        $aliasesFetched = $transport->fetchGmailAliases(true);

        if ($aliasesFetched) {
            $aliases = SmtpProviderAlias::where('smtp_provider_id', $provider->id)->get();
            
            return response()->json([
                'status' => 'success',
                'data' => $aliases,
                'message' => 'Aliases refreshed successfully.',
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Failed to refresh aliases.'
        ]);
    }


    private function sendTestMail($provider, $email)
    {
        if ($provider->slug === 'office-365') {
            $transport = (new Office365MailTransport($provider));
            $subject = '';
        }else {
            $transport = (new GoogleMailTransport($provider));
            $subject = " (#".time().")";
        }
        
        $globalSetting = GlobalSetting::select('display_name')->first();

        $message = (new \Swift_Message(decodeHTMLEntity($globalSetting->display_name)." - Test Mail".$subject))
            ->setFrom($email)
            ->setTo($email)
            ->setBody("Please ignore this mail. This mail is sent to verify the connection of ".$provider->provider_name.' on '.$globalSetting->display_name. '.');

        $transport->send($message);
    }

    private function storeToken($response, $provider)
    {
        $provider = SmtpProvider::where('slug', $provider)->first();

        if ($provider) {
            $tokenData = [
                'access_token' => $response->token,
                'refresh_token' => $response->refreshToken,
                'token_expires' => $response->expiresIn ? time() + $response->expiresIn : null,
                'connected' => 1,
                'from_address' => $response->email,
                'from_name' => $response->name
            ];

            $provider->update($tokenData);

            return true;
        }
        return false;
    }

    private function getDomainNameWithTenant($tenantId)
    {
        if (env('TENANCY_ENABLED')) {
            $tenant = Tenant::where('id', $tenantId)->first();

            if (!$tenant) {
                return false;
            }

            $domain = $tenant->domains()->first();

            if (!$domain) {
                return false;
            }

            $protocol = request()->secure() ? 'https://' : 'http://';

            return $protocol . $domain->domain;
        } else {
            return request()->getSchemeAndHttpHost();
        }
    }

    private function getProviderConfig($slug)
    {
        if ($slug === 'office-365') {
            return new \SocialiteProviders\Manager\Config(
                config('services.microsoft.client_id'),
                config('services.microsoft.client_secret'),
                config('services.office-365-smtp.redirect'),
                ['tenant' => 'common']
            );
        }

        if ($slug === 'gmail') {
            return new \SocialiteProviders\Manager\Config(
                config('services.gmail-smtp.client_id'),
                config('services.gmail-smtp.client_secret'),
                config('services.gmail-smtp.redirect')
            );
        }
    }

    private function resetManualMailSettings()
    {
        $setting = MailSetting::first();

        if ($setting) {
            $data = [
                'mail_host' => null,
                'mail_port' => null,
                'mail_from_address' => null,
                'mail_from_name' => null,
                'mail_username' => null,
                'mail_password' => null,
                'mail_encryption' => null
            ];

            $setting->update($data);
        }

        return true;
    }
}
