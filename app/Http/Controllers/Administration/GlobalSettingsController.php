<?php

namespace App\Http\Controllers\Administration;

use Inertia\Inertia;
use App\Traits\Timezone;
use LdapRecord\Container;
use App\Saml2Sp\Saml2Auth;
use Illuminate\Http\Request;
use App\Utils\RegularFunctions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\UserManagement\Admin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Crypt;
use OneLogin\Saml2\IdPMetadataParser;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use App\LicenseBox\LicenseBoxExternalAPI;
use Illuminate\Support\Facades\Validator;
use App\Models\GlobalSettings\LdapSetting;
use App\Models\GlobalSettings\MailSetting;
use App\Models\GlobalSettings\SamlSetting;
use App\Models\GlobalSettings\SmtpProvider;
use App\Models\GlobalSettings\GlobalSetting;
use Illuminate\Validation\ValidationException;
use App\Models\GlobalSettings\SmtpProviderAlias;
use App\Models\RiskManagement\RiskMatrix\RiskMatrixScore;
use App\Models\RiskManagement\RiskMatrix\RiskMatrixImpact;
use App\Models\RiskManagement\RiskMatrix\RiskScoreLevelType;
use App\Models\RiskManagement\RiskMatrix\RiskMatrixLikelihood;
use App\Models\Administration\OrganizationManagement\Organization;
use App\Models\RiskManagement\RiskMatrix\RiskMatrixAcceptableScore;

class GlobalSettingsController extends Controller
{
    use Timezone;

    public $validation_required='validation.required';
    public $validation_integer='validation.integer';
    public $validation_url='validation.url';


    public function index(Request $request)
    {
        $sessionExpiryTimes = [
            'null' => 'Never',  
            '15' => '15 minutes',
            '30' => '30 minutes',
            '60' => '1 hour',
        ];

        // Timezone array from Timezone Traits
        $timezones = $this->appTimezone();

        // mail settings
        $mailSettings = MailSetting::first();
        $is_mail_testable = true;

        if(!$mailSettings){
            $mailSettings = new MailSetting;
            $mailSettings->mail_host = '';
            $mailSettings->mail_port = '';
            $mailSettings->mail_encryption = strtolower('ssl');
            $mailSettings->mail_from_address = '';
            $mailSettings->mail_from_name = '';
            $is_mail_testable = false;
        }
        else
        {
            if($mailSettings->mail_host == null || $mailSettings->mail_port == null || $mailSettings->mail_username == null || $mailSettings->mail_password == null || $mailSettings->mail_from_address == null || $mailSettings->mail_from_name == null)
            {
                $is_mail_testable = false;
            }
        }

        //oauth smtp settings
        $smtpProviders = SmtpProvider::select('provider_name', 'slug', 'connected')->get();
        $connectedSmtpProvider = SmtpProvider::where('connected', 1)
            ->select('id', 'provider_name', 'slug', 'connected', 'from_name', 'from_address')
            ->first();

        $aliases = [];
        
        if ($connectedSmtpProvider) {
            $aliases = SmtpProviderAlias::where('smtp_provider_id', $connectedSmtpProvider->id)->get();
        }
        
        // ldap settings
        $ldapSettings = LdapSetting::first();

        // ldap settings
        $samlSetting = SamlSetting::first();

        // organization settings
        $organizations = Organization::with(['departments' => function ($query) {
            $query->where('parent_id', 0);
        }])->get();


        /* Risk matrix likelihoods */
        $riskMatrixLikelihoods = RiskMatrixLikelihood::all(['id', 'name', 'index']);
        $riskMatrixImpacts = RiskMatrixImpact::all(['id', 'name', 'index']);
        $riskMatrixScores = RiskMatrixScore::orderBy('likelihood_index', 'ASC')
            ->orderBy('impact_index', 'ASC')->select(['id', 'score', 'impact_index', 'likelihood_index'])->get()->split(count($riskMatrixLikelihoods));
        $riskScoreLevelTypes = RiskScoreLevelType::with(['levels' => function ($query) {
            $query->select('id', 'name', 'max_score', 'color', 'level_type');
        }])->select(['id', 'level', 'is_active'])->get();
        $riskMatrixAcceptableScore = RiskMatrixAcceptableScore::select('id', 'score')->first();

        $license=[];
        /** License Detail */
        if(env('LICENSE_ENABLED')){
            $licenseDetails = new LicenseBoxExternalAPI();
            $license['currentVersion'] = $licenseDetails->get_current_version();
            $verificationWithDetails = $licenseDetails->verify_license();
            $license['licensedTo'] = ucFirst(Config::get('license.license.client_name'));
           
            $license['licenseExpiryDate'] = $verificationWithDetails['data'];
        }
        
        return Inertia::render('global-settings/GlobalSettings', [
            'timezones' => $timezones,
            'sessionExpiryTimes' => $sessionExpiryTimes,
            'mailSettings' => $mailSettings,
            'smtpProviders' => $smtpProviders,
            'connectedSmtpProvider' => $connectedSmtpProvider,
            'aliases' => $aliases,
            'ldapSettings' => $ldapSettings,
            'samlSetting' => $samlSetting,
            'organizations' => $organizations,
            'riskMatrixLikelihoods' => $riskMatrixLikelihoods,
            'riskMatrixImpacts' => $riskMatrixImpacts,
            'riskMatrixScores' => $riskMatrixScores,
            'riskScoreLevelTypes' => $riskScoreLevelTypes,
            'riskMatrixAcceptableScore' => $riskMatrixAcceptableScore,
            'is_mail_testable' => $is_mail_testable,
            'license'=>$license,
            'form_actions' => [
                'global_settings' => route('global-settings.store'),
                'mail_settings' => route('global-settings.mail-settings'),
                'ldap_settings' => route('global-settings.ldap-settings'),
                'saml_settings' => [
                    'upload' => route('global-settings.saml-settings.saml-provider-metadata.upload'),
                    'remote_import' => route('global-settings.saml-settings.saml-provider-metadata.import'),
                    'remove' => route('global-settings.saml-settings.saml-provider-metadata.remove'),
                    'manual' => route('global-settings.saml-settings')
                ]
            ],
            'connection_test_routes' => [
                'mail_settings' => route('global-settings.test-mail-connection'),
                'ldap_settings' => route('global-settings.test-ldap-connection')
            ],
            'saml_information' => [
                'metadata' => route('saml2.metadata'),
                'acs' => route('saml2.acs'),
                'login' => route('saml2.login'),
                'sls' => route('saml2.sls'),
                'download' => route('global-settings.saml-settings.download.sp-metadata'),
            ]
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'display_name' => 'required|max:191',
            'primary_color' => 'required|max:191',
            'secondary_color' => 'required|max:191',
            'default_text_color' => 'required|max:191',
            'company_logo' => 'nullable|image|dimensions:min_width=70,min_height=71,max_width=595,max_height=600',
            'small_company_logo' => 'nullable|image',
            'favicon' => 'nullable|image',
            'allow_document_upload' => 'required',
            'allow_document_link' => 'required',
            'session_timeout' => 'required|in:null,15,30,60',
        ], [
            'display_name.required' => __($this->validation_required, ['attribute' => 'Display Name']),
            'primary_color.required' => __($this->validation_required, ['attribute' => 'Primary Color']),
            'secondary_color.required' => __($this->validation_required, ['attribute' => 'Secondary Color']),
            'default_text_color.required' => __($this->validation_required, ['attribute' => 'Default Text Color']),
            'allow_document_upload.required' => __($this->validation_required, ['attribute' => 'Allow Document Upload']),
            'allow_document_link.required' => __($this->validation_required, ['attribute' => 'Allow Document Link']),
        ]);

        $inputs = $request->only('display_name', 'timezone', 'primary_color', 'secondary_color', 'default_text_color', 'secure_mfa_login', 'allow_document_upload', 'allow_document_link');

        if ($request->session_timeout == 'null') {
            $inputs['session_timeout'] = null;
        } else {
            $inputs['session_timeout'] = $request->session_timeout;
        }

        $globalSetting = GlobalSetting::first();

        if ($request->hasFile('company_logo')) {
            // $companyLogoPath = $request->file('company_logo')->store(
            //     'public/global_settings/' . $request->user()->id
            // );

            // $pathArray = explode('/', $companyLogoPath);
            // $pathArray[0] = '';

            // $inputs['company_logo'] = implode('/', $pathArray);

            // fix to revert
            if(config('filesystems.default') == 's3'){
                if(env('TENANCY_ENABLED')){
                    $companyLogoPath = $request->file('company_logo')->store(
                        tenant('id').'/public/global_settings/' . $request->user()->id,'s3-public'
                    );
                }
                else{
                    $companyLogoPath = $request->file('company_logo')->store(
                        '/public/global_settings/' . $request->user()->id,'s3-public'
                    );
                }
    
                // deleting pervious file
                $db_global_setting=DB::table('account_global_settings')->first();
                if (Storage::disk('s3-public')->exists($db_global_setting->company_logo)) {
                    Storage::disk('s3-public')->delete($db_global_setting->company_logo);
                }
    
                $inputs['company_logo'] = $companyLogoPath;
            }
            else{
                $companyLogoPath = $request->file('company_logo')->store(
                    'public/global_settings/' . $request->user()->id
                );

                $pathArray = explode('/', $companyLogoPath);
                $pathArray[0] = '';

                $inputs['company_logo'] = implode('/', $pathArray);
            }
            
        }

        if ($request->hasFile('small_company_logo')) {
            $smallCompanyLogoPath = $request->file('small_company_logo')->store(
                'public/global_settings/' . $request->user()->id
            );

            $pathArray = explode('/', $smallCompanyLogoPath);
            $pathArray[0] = '';

            $inputs['small_company_logo'] = implode('/', $pathArray);
        }

        if ($request->hasFile('favicon')) {
            $favicon = $request->file('favicon')->store(
                'public/global_settings/' . $request->user()->id
            );

            $pathArray = explode('/', $favicon);
            $pathArray[0] = '';

            $inputs['favicon'] = implode('/', $pathArray);
        }

        $globalSetting->fill($inputs);

        $updated = $globalSetting->update();

        Log::info('User has updated global settings');
        if ($updated) {
            return redirect()->back()->with([
                'success' => 'Global settings updated successfully.',
                'activeTab' => 'globalSettings',
            ]);
        }
    }

    public function updateMailSetting(Request $request)
    {
        try {
            $mailSetting = MailSetting::first();

            $mailSetting = $mailSetting ?: new MailSetting();

            $inputs = $request->only('mail_host', 'mail_port', 'mail_username', 'mail_password', 'mail_encryption', 'mail_from_address', 'mail_from_name');
            
            if ($inputs['mail_host'] || $inputs['mail_port'] || $inputs['mail_username'] || $inputs['mail_password'] || $inputs['mail_from_address'] || $inputs['mail_from_name']) {
                $request->validate([
                    'mail_host' => 'required',
                    'mail_port' => 'required|integer',
                    'mail_username' => 'required',
                    'mail_encryption' => 'required',
                    'mail_from_address' => 'required|email',
                    'mail_from_name' => 'required',
                    'mail_password' => [function ($attribute, $value, $fail) use ($mailSetting, $request) {
                        if (is_null($mailSetting->mail_password) && !$request->mail_password) {
                            $fail('The SMTP Password field is required');
                        }
                    }],
                ], [
                    'mail_host.required' => __($this->validation_required, ['attribute' => 'SMTP Host']),
                    'mail_port.required' => __($this->validation_required, ['attribute' => 'SMTP Port']),
                    'mail_port.integer' => __($this->validation_integer, ['attribute' => 'SMTP Port']),
                    'mail_username.required' => __($this->validation_required, ['attribute' => 'SMTP Username']),
                    'mail_encryption.required' => __($this->validation_required, ['attribute' => 'SMTP Encryption']),
                    'mail_from_address.required' => __($this->validation_required, ['attribute' => 'From Email Address']),
                    'mail_from_name.required' => __($this->validation_required, ['attribute' => 'From Name']),
                ]);
            }
            else
            {
                $inputs['mail_encryption'] = null;
            }
    
            $inputs['mail_password'] = encrypt($inputs['mail_password']);
    
            $mailSetting->fill($inputs);
            
            // Create the Transport
            Log::info('System is testing mail connection');

            $transport = (new \Swift_SmtpTransport($mailSetting['mail_host'], $mailSetting['mail_port'], $mailSetting['mail_encryption']))
             ->setUsername($mailSetting['mail_username'])
             ->setPassword($mailSetting['mail_password']);
    
            // Create the Mailer using your created Transport
            $mailer = new \Swift_Mailer($transport);
    
            $mailer->getTransport()->start();

            Log::info('User mail connection succeeded');

            $updated = $mailSetting->save();
    
            if ($updated) {
                $this->resetSMTPOauthConnection();
                Log::info('User has updated SMTP Settings');

                return redirect()->back()->with([
                    'success' => 'SMTP Settings updated successfully.',
                    'activeTab' => 'smtpSettings',
                ]);
            }
        } catch (\Exception $th) {
            
            Log::info('User mail connection failed');

            return redirect()->back()->with([
                'error' => 'Failed to process request. Please check SMTP authentication connection.',
                'activeTab' => 'smtpSettings',
            ]);
        }
    }

    public function testMailConnection(Request $request)
    {
        Log::info('User is testing mail connection');
        try {
            $mailConfig = \Config::get('mail');

            if (!$mailConfig['host'] || !$mailConfig['port'] || !$mailConfig['encryption'] || !$mailConfig['username'] || !$mailConfig['password']) {
                return redirect()->back()->with([
                    'error' => 'SMTP Settings are not configured.',
                    'activeTab' => 'smtpSettings',
                ]);
            }

            // Create the Transport
            $transport = (new \Swift_SmtpTransport($mailConfig['host'], $mailConfig['port'], $mailConfig['encryption']))
                ->setUsername($mailConfig['username'])
                ->setPassword($mailConfig['password']);

            // Create the Mailer using your created Transport
            $mailer = new \Swift_Mailer($transport);

            $mailer->getTransport()->start();

            Log::info('User mail connection succeeded');
            return redirect()->back()->with([
                'success' => 'Connection to SMTP established successfully.',
                'activeTab' => 'smtpSettings',
            ]);
        } catch (\Exception $exception) {

            Log::info('User mail connection failed');
            return redirect()->back()->with([
                'error' => 'Failed to process request. Please check SMTP authentication connection.',
                'activeTab' => 'smtpSettings',
            ]);
        }
    }
    public function testLdapConnection(Request $request)
    {
        Log::info('User is testing LDAP connection');
        try {
            // ldap settings
            $ldapSettings = LdapSetting::first();

            $connection = Container::getConnection('ldap');

            $auth = $connection->auth()->attempt($ldapSettings->base_dn, $ldapSettings->password);

            // verify binding
            if ($auth) {
                Log::info('User LDAP connection succeeded');
                return redirect()->back()->with([
                    'success' => 'Connection to LDAP established successfully.',
                    'activeTab' => 'ldapSettings',
                ]);
            } else {
                Log::info('User LDAP connection failed');
                return redirect()->back()->with([
                    'error' => 'Failed to process request. Please check LDAP authentication connection.',
                    'activeTab' => 'ldapSettings',
                ]);
            }
        } catch (\Exception $exception) {
            Log::info('User LDAP connection failed');
            return redirect()->back()->with([
                'error' => 'Failed to process request. Please check LDAP authentication connection.',
                'activeTab' => 'ldapSettings',
            ]);
        }
    }

    public function updateLdapSetting(Request $request)
    {
        $request->validate([
            'hosts' => 'required',
            'base_dn' => 'required',
            'username' => 'required',
            'bind_password' => 'required',
            'port' => 'nullable|integer',
            'use_ssl' => 'nullable|in:1,0',
            'version' => 'nullable|integer',
            'map_first_name_to' => 'required',
            'map_last_name_to' => 'required',
            'map_email_to' => 'required',
        ], [
            'bind_password.required' => __($this->validation_required, ['attribute' => 'Password']),
            'hosts.required' => __($this->validation_required, ['attribute' => 'Hosts']),
            'base_dn.required' => __($this->validation_required, ['attribute' => 'Base DN']),
            'username.required' => __($this->validation_required, ['attribute' => 'Username']),            
            'port.integer' => __($this->validation_integer, ['attribute' => 'Port']),
            'map_first_name_to.required' => __($this->validation_required, ['attribute' => 'First Name']),
            'map_last_name_to.required' => __($this->validation_required, ['attribute' => 'Last Name']),
            'map_email_to.required' => __($this->validation_required, ['attribute' => 'Email']),
        ]);

        $ldapSettings = LdapSetting::first();

        $inputs = $request->toArray();
        $inputs['use_ssl'] = isset($inputs['use_ssl']) ? ($inputs['use_ssl'] == '1' ? true : false) : false;
        $inputs['password'] = $request->bind_password;

        if (is_null($ldapSettings)) {
            $created = LdapSetting::create($inputs);
            Log::info('User has updated LDAP Settings');

            return redirect()->back()->with([
                'success' => 'LDAP setting configured successfully.',
                'activeTab' => 'ldapSettings',
            ]);
        }

        $updated = $ldapSettings->update($inputs);

        if (!$updated) {
            // code...
            Log::info('User could not update LDAP Settings');
        }
        Log::info('User has updated LDAP Settings');

        return redirect()->back()->with([
            'success' => 'LDAP setting updated successfully.',
            'activeTab' => 'ldapSettings',
        ]);
    }

    public function updateSamlSetting(Request $request)
    {
        $validator=Validator::make($request->all(),[
            'sso_provider' => 'required',
            'entity_id' => 'required',
            'sso_url' => 'required|url',
            'slo_url' => 'required|url',
            'certificate' => 'required',
        ],[
            'sso_provider.required' => __($this->validation_required, ['attribute' => 'SSO Provider']),
            'entity_id.required' => __($this->validation_required, ['attribute' => 'Entity ID']),
            'sso_url.required' => __($this->validation_required, ['attribute' => 'SSO URL']),
            'sso_url.url' => __($this->validation_url, ['attribute' => 'SSO URL']),
            'slo_url.required' => __($this->validation_required, ['attribute' => 'SLO URL']),
            'slo_url.url' => __($this->validation_url, ['attribute' => 'SLO URL']),
            'certificate.required' => __($this->validation_required, ['attribute' => 'Certificate']),
        ]);
        if($validator->fails()){
            return redirect()->back()->with([
                'activeTab' => 'samlSettings'
            ])->withErrors($validator)->withInput();
        }

        $samlSetting = SamlSetting::first();

        $samlSetting = $samlSetting ?: new SamlSetting();

        $inputs = $request->only('sso_provider', 'entity_id', 'sso_url', 'slo_url', 'certificate');

        $samlSetting->fill($inputs);

        $updated = $samlSetting->save();

        if ($updated) {
            Log::info('User has updated SAML Settings');
            return redirect()->back()->with([
                'success' => 'SAML settings updated successfully.',
                'activeTab' => 'samlSettings',
            ]);
        }
    }

    /***
     * upload Identity provider metadata
     */
    public function uploadSamlProviderMetadata(Request $request)
    {
        Log::info('User is attempting to upload SAML provider metadata');
        $request->validate([
            'saml_provider_metadata_file' => 'required|file|mimes:xml|max:1000',
        ]);

        $metadataInfo = IdPMetadataParser::parseFileXML($request->file('saml_provider_metadata_file'));

        return $this->updateSamlSettingsFromMetadata($metadataInfo, 'file_upload');
    }

    /***
     * import Identity provider metadata
     */
    public function importSamlProviderMetadata(Request $request)
    {
        Log::info('User is attempting to import remote SAML provider metadata');
        $request->validate([
            'saml_provider_remote_metadata' => 'required|url',
        ], [
            'saml_provider_remote_metadata.url' => 'The saml provider remote metadata field must be a url',
        ]);

        $metadataInfo = IdPMetadataParser::parseRemoteXML($request->saml_provider_remote_metadata);

        return $this->updateSamlSettingsFromMetadata($metadataInfo, 'url_import');
    }

    /**
     *  helper method used to update saml settings.
     */
    private function updateSamlSettingsFromMetadata($metadataInfo, $metadataSource)
    {
        $errorsMsgs = [];

        if (empty($metadataInfo)) {
            $errorMsg = ['Uploaded metadata is not a valid Identity provider metadata '];

            if ($metadataSource == 'file_upload') {
                $errorsMsgs['saml_provider_metadata_file'] = $errorMsg;
            } else {
                $errorsMsgs['saml_provider_remote_metadata'] = $errorMsg;
            }

            throw ValidationException::withMessages($errorsMsgs);
        }

        $metadataInfo = $metadataInfo['idp'];

        if (empty($metadataInfo['entityId'])) {
            $errorMsg = ['Uploaded metadata is missing entityId'];

            if ($metadataSource == 'file_upload') {
                $errorsMsgs['saml_provider_metadata_file'] = $errorMsg;
            } else {
                $errorsMsgs['saml_provider_remote_metadata'] = $errorMsg;
            }
        }

        // sso service key validation
        if (empty($metadataInfo['singleSignOnService']) || empty($metadataInfo['singleSignOnService']['url'])) {
            $errorMsg = ['Uploaded metadata is missing singleSignOnService'];

            if ($metadataSource == 'file_upload') {
                $errorsMsgs['saml_provider_metadata_file'] = $errorMsg;
            } else {
                $errorsMsgs['saml_provider_remote_metadata'] = $errorMsg;
            }
        }

        // slo service key validation
        if (empty($metadataInfo['singleLogoutService']) || empty($metadataInfo['singleLogoutService']['url'])) {
            $errorMsg = ['Uploaded metadata is missing singleLogoutService'];

            if ($metadataSource == 'file_upload') {
                $errorsMsgs['saml_provider_metadata_file'] = $errorMsg;
            } else {
                $errorsMsgs['saml_provider_remote_metadata'] = $errorMsg;
            }
        }

        // x509cert validation

        $certificate = '';
        $is_x509certMulti = false;

        if (isset($metadataInfo['x509cert'])) {
            $certificate = $metadataInfo['x509cert'];
        } elseif (isset($metadataInfo['x509certMulti'])) {
            $certificate = json_encode($metadataInfo['x509certMulti']);
            $is_x509certMulti = true;
        } else {
            if (empty($metadataInfo['x509cert'])) {
                $errorMsg = ['Uploaded metadata is missing x509cert'];

                if ($metadataSource == 'file_upload') {
                    $errorsMsgs['saml_provider_metadata_file'] = $errorMsg;
                } else {
                    $errorsMsgs['saml_provider_remote_metadata'] = $errorMsg;
                }
            }
        }

        if (count($errorsMsgs) > 0) {
            throw ValidationException::withMessages($errorsMsgs);
        }

        // creating if already does not exist
        $samlSetting = SamlSetting::first();
        $samlSetting = $samlSetting ?: new SamlSetting();

        $samlSetting->fill([
            'sso_provider' => 'Update this field with correct sso provider',
            'entity_id' => $metadataInfo['entityId'],
            'sso_url' => $metadataInfo['singleSignOnService']['url'],
            'slo_url' => $metadataInfo['singleLogoutService']['url'],
            'certificate' => $certificate,
            'is_x509certMulti' => $is_x509certMulti,
        ]);

        $updated = $samlSetting->save();

        if ($updated) {
            Log::info('User has uploaded SAML provider metadata');
            return redirect()->back()->with([
                'success' => 'SAML settings updated successfully.',
                'activeTab' => 'samlSettings',
            ]);
        }
    }

    /**
     * download sp metadata.
     */
    public function downloadSpMetadata(Saml2Auth $saml2Auth)
    {
        Log::info('User has downloaded SAML Metadata');
        $contents = $saml2Auth->getMetadata();
        $filename = 'metadata.xml';

        return response()->streamDownload(function () use ($contents) {
            echo $contents;
        }, $filename);
    }


    /**
     * download sp metadata.
     */
    public function removeSamlSettings()
    {
        SamlSetting::truncate();

        //reset manual user auth_method to Manual when saml is removed
        Admin::where('is_manual_user', 1)->update(['auth_method' => 'Manual','is_sso_auth' => 0]);

        Log::info('User has removed SAML provider metadata');
        
        return back()->with([
            'success' => 'SAML settings removed successfully.',
            'activeTab' => 'samlSettings',
        ]);
    }

    private function resetSMTPOauthConnection()
    {
        $data = [
            'access_token' => null,
            'refresh_token' => null,
            'token_expires' => null,
            'connected' => 0,
            'from_address' => null,
            'from_name' => null
        ];

        SmtpProvider::where('connected', 1)->update($data);

        return true;
    }
}
