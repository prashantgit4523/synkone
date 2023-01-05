<?php

//namespace App\Helpers; // define Helper scope
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;


if(!function_exists('decodeHTMLEntity')) {

    function decodeHTMLEntity($data)
    {
        return html_entity_decode($data, ENT_QUOTES, 'utf-8');
    }
}

if(!function_exists('checkForSAMLConfigurationStatus')) {
    function checkForSAMLConfigurationStatus()
    {
        $isSsoConfigured = false;

            if (\Schema::hasTable('saml_settings')) {
                $samlSettings = \DB::table('saml_settings')->first();

                if (
                    $samlSettings
                    && isset($samlSettings->sso_provider)
                    && isset($samlSettings->entity_id)
                    && isset($samlSettings->sso_url)
                    && isset($samlSettings->slo_url)
                    && isset($samlSettings->certificate)
                ) { //checking if table is not empty
                    $IdpConfigKey = 'saml2.ebdaa_idp_settings.idp.';

                    \Config::set($IdpConfigKey . 'entityId', $samlSettings->entity_id);
                    \Config::set($IdpConfigKey . 'singleSignOnService.url', $samlSettings->sso_url);
                    \Config::set($IdpConfigKey . 'singleLogoutService.url', $samlSettings->slo_url);

                    // setting certificate
                    if ($samlSettings->is_x509certMulti) {
                        $x509certMulti = json_decode($samlSettings->certificate, true);

                        \Config::set($IdpConfigKey . 'x509certMulti', $x509certMulti);
                    } else {
                        \Config::set($IdpConfigKey . 'x509cert', $samlSettings->certificate);
                    }

                    $isSsoConfigured = true;
                }
            }

        return $isSsoConfigured;
    }
}

if(!function_exists('callArtisanCommand')) {

    function callArtisanCommand($command)
    {
        if(env('TENANCY_ENABLED')){
            if(env('PHP_CONFLICT') && env('PHP8_BINARY')){
                $cmd= "cd " . base_path() . " && ".env("PHP8_BINARY")." artisan tenants:run ".$command." --tenants=".tenant('id'). " > /dev/null 2>/dev/null &";
            }
            else{
                $cmd= "cd " . base_path() . " && php artisan tenants:run ".$command." --tenants=".tenant('id'). " > /dev/null 2>/dev/null &";
            }
        }else{
            if(env('PHP_CONFLICT') && env('PHP8_BINARY')){
                $cmd= "cd " . base_path() . " && ".env("PHP8_BINARY")." artisan ".$command." > /dev/null 2>/dev/null &";
            }
            else{
                $cmd= "cd " . base_path() . " && php artisan ".$command." > /dev/null 2>/dev/null &";
            }
        }
        return shell_exec($cmd);
    }
}

if(!function_exists('writeLog')){
     /**
     * This function write log file. 
     * @param specificationName The name of the specification: emergency, alert, critical, error, warning, notice, info and debug.
     * @param message The message to be logged.
     */
    function writeLog($specificationName,$message): void
    {
        $specificationName = Str::lower($specificationName);
        Log::$specificationName($message);
    }
}
