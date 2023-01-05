<?php

namespace App\ScheduledTasks;
use Carbon\Carbon;
use App\Nova\Model\Tenant;
use Illuminate\Support\Facades\Storage;
use App\Models\GlobalSettings\SmtpProvider;


trait TenantScheduleTrait {

    /**
     * Setup tenat content for mail
     * @param tenant_id
     */
    public function SetUpTenantMailContent($tenant_id,$dbConfig = true) {
        $mailView = 'views/vendor/mail';

        $tenant=Tenant::where('id',$tenant_id)->first();
        $domain=$tenant->domains()->first();
        if(env('APP_ENV')=="development" || env('APP_ENV')=="production"){
            \URL::forceRootUrl('https://'.$domain->domain);
        }
        else{
            \URL::forceRootUrl('http://'.$domain->domain);
        }

        if($dbConfig){
            $this->setTenantDb($tenant_id);
        }
        $globalSettings = \DB::table('account_global_settings')->first();

        if(config('filesystems.default') != 'local' && $globalSettings->company_logo!='assets/images/ebdaa-Logo.png'){
            $disk = Storage::disk('s3-public');
            $globalSettings->company_logo = $disk->url($globalSettings->company_logo);
        }
      
        \View::share('globalSetting', $globalSettings);


        $provider = \DB::table('smtp_providers')->where('connected', 1)->first();

        if ($provider) {
            $driver = $provider->slug === 'office-365' ? 'office365mail' : 'googlemail';

            $config = [
                'driver' => $driver,
                    'markdown' => [
                        'default' => 'markdown',
                        'paths' => [resource_path($mailView)],
                    ],
                ];

            \Config::set('mail', $config);

            (new \Illuminate\Mail\MailServiceProvider(app()))->register();

        } elseif (\Schema::hasTable('mail_settings')) {
            $mailSettings = \DB::table('mail_settings')->first();

            if ($mailSettings && isset($mailSettings->mail_host) && isset($mailSettings->mail_username) && isset($mailSettings->mail_password) && isset($mailSettings->mail_encryption)) { //checking if table is not empty
                
                try{
                    $mail_password = decrypt($mailSettings->mail_password);
                }catch(\Exception $e){
                    $mail_password = '';
                    \Log::error('Not able to decrypt the password due to APP_KEY change.');
                }

                $config = [
                    'driver' => $mailSettings->mail_driver ?: env('MAIL_DRIVER'),
                    'host' => $mailSettings->mail_host,
                    'port' => $mailSettings->mail_port ?: env('MAIL_PORT'),
                    'from' => [
                        'address' => $mailSettings->mail_from_address ?: env('MAIL_FROM_ADDRESS', 'Ebdaa@example.com'),
                        'name' => $mailSettings->mail_from_name ?: env('MAIL_FROM_NAME', 'Ebdaa GRC'),
                    ],
                    'encryption' => $mailSettings->mail_encryption,
                    'username' => $mailSettings->mail_username,
                    'password' => $mail_password,
                    'markdown' => [
                        'default' => 'markdown',
                        'paths' => [resource_path($mailView)],
                    ],
                ];

                \Config::set('mail', $config);

                (new \Illuminate\Mail\MailServiceProvider(app()))->register();
            } else {
                $config = [
                    'driver' => env('MAIL_MAILER'),
                    // 'stream' => [
                    //     'ssl' => [
                    //         'allow_self_signed' => true,
                    //         'verify_peer' => false,
                    //         'verify_peer_name' => false,
                    //     ],
                    // ],
                    'host' => env('MAIL_HOST'),
                    'port' => env('MAIL_PORT'),
                    'from' => [
                        'address' => env('MAIL_FROM_ADDRESS', 'grc@ebdaa.ae'),
                        'name' => env('MAIL_FROM_NAME', 'CyberArrow GRC'),
                    ],
                    'encryption' => env('MAIL_ENCRYPTION'),
                    'username' => env('MAIL_USERNAME'),
                    'password' => env('MAIL_PASSWORD'),
                    'markdown' => [
                        'default' => 'markdown',
                        'paths' => [resource_path($mailView)],
                    ],
                ];

                \Config::set('mail', $config);
                (new \Illuminate\Mail\MailServiceProvider(app()))->register();
            }
        }

        if($dbConfig){
            $this->unsetTenantDb();
        }
    }

    /**
     * setting up tenant db for console commands
     */
    public function setTenantDb($tenant_id){
        \Config::set('database.connections.mysql.database', 'tenant'.$tenant_id);
        \DB::purge('mysql');
    }

    /**
     * unsetting tenant db for console commands
     */
    public function unsetTenantDb(){
        \Config::set('database.connections.mysql.database', env('DB_DATABASE'));
        \DB::purge('mysql');
    }

    public function checkIfSubscriptionExpired($tenant_id){
        $expired = false;
        $tenant=Tenant::where('id',$tenant_id)->first();
        $tenant_subs_expiration=$tenant->subscription_expiry_date;
        $nowDate = Carbon::now();
        if($nowDate->gt($tenant_subs_expiration)){
            $expired =  true;
        }
        return $expired;
    }
}