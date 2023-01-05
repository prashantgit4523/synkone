<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\GlobalSettings\SmtpProvider;

class MailConfigServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register()
    {
    }

    /**
     * Bootstrap services.
     */
    public function boot()
    {
        try {
            $provider = \DB::table('smtp_providers')->where('connected', 1)->first();

            if ($provider) {
                $driver = $provider->slug === 'office-365' ? 'office365mail' : 'googlemail';

                $config = [
                    'driver' => $driver,
                        'markdown' => [
                            'default' => 'markdown',
                            'paths' => [resource_path('views/vendor/mail')],
                        ],
                    ];

                \Config::set('mail', $config);
            }
            
            if (\Schema::hasTable('mail_settings')) {
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
                        // 'stream' => [
                        //     'ssl' => [
                        //         'allow_self_signed' => true,
                        //         'verify_peer' => false,
                        //         'verify_peer_name' => false,
                        //     ],
                        // ],
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
                            'paths' => [resource_path('views/vendor/mail')],
                        ],
                    ];

                    \Config::set('mail', $config);
                }
            //     else{
            //         $config = [
            //             'driver' => env('MAIL_MAILER'),
            //             // 'stream' => [
            //             //     'ssl' => [
            //             //         'allow_self_signed' => true,
            //             //         'verify_peer' => false,
            //             //         'verify_peer_name' => false,
            //             //     ],
            //             // ],
            //             'host' => env('MAIL_HOST'),
            //             'port' => env('MAIL_PORT'),
            //             'from' => [
            //                 'address' => env('MAIL_FROM_ADDRESS', 'grc@ebdaa.ae'),
            //                 'name' => env('MAIL_FROM_NAME', 'CyberArrow GRC'),
            //             ],
            //             'encryption' => env('MAIL_ENCRYPTION'),
            //             'username' => env('MAIL_USERNAME'),
            //             'password' => env('MAIL_PASSWORD'),
            //             'markdown' => [
            //                 'default' => 'markdown',
            //                 'paths' => [resource_path('views/vendor/mail')],
            //             ],
            //         ];

            //         \Config::set('mail', $config);
            //     }
            }
        } catch (\Exception $exception) {
            \Log::error($exception);
        }
    }
}
