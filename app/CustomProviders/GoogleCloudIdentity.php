<?php

namespace App\CustomProviders;

use GuzzleHttp\HandlerStack;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\CustomProviders\Interfaces\ICustomAuth;
use App\Traits\Integration\IntegrationApiTrait;
use Google\Auth\Middleware\AuthTokenMiddleware;
use Google\Auth\Credentials\ServiceAccountCredentials;

class GoogleCloudIdentity extends CustomProvider implements ICustomAuth
{
    use IntegrationApiTrait;

    public function __construct()
    {
        parent::__construct('google-cloud-identity', false);
    }

    public function attempt(array $fields): bool
    {
        try {
            $domain = $this->getDomainName($fields['domain']);
            
            $this->callApi("https://admin.googleapis.com/admin/directory/v1/users?domain=".$domain, $fields);
            $this->connect($this->provider, $fields);

            return true;
        } catch (\Exception $e) {
            Log::error('GoogleCloudIdentity Attemp to connect failed: '. $e->getMessage());
        }

        return false;
    }

    public function getUsersLists(): ?array
    {
        try {
            $domain = $this->getDomainName($this->fields['domain']);
            
            $response = $this->callApi('https://admin.googleapis.com/admin/directory/v1/users?domain='.$domain);

            if ($response && $response->ok()) {
                $body = json_decode($response->body(), true);
                
                return $body ? $body['users'] : [];
            }
        } catch (\Exception $e) {
            Log::error('GoogleCloudIdentity getUsersLists implementation failed: '. $e->getMessage());
        }
        return [];
    }

    public function findUser($email): ?array
    {
        try {
            $response = $this->callApi('https://admin.googleapis.com/admin/directory/v1/users/'.$email);

            if ($response && $response->ok()) {
                return json_decode($response->body(), true);
            }
        } catch (\Exception $e) {
            Log::error('GoogleCloudIdentity findUser implementation failed: '. $e->getMessage());
        }
        return [];
    }

    public function callApi($url, $fields = null)
    {
        $fields = json_decode($fields['private_key'] ?? $this->fields['private_key'], true);
        $user_email = $fields['email'] ?? $this->fields['email'];
        
        $sa = new ServiceAccountCredentials(config('services.google-cloud-identity.scopes'), $fields, $user_email);

        $middleware = new AuthTokenMiddleware($sa);
        $stack = HandlerStack::create();
        $stack->push($middleware);

        return Http::withOptions([
            'handler' => $stack,
            'base_uri' => null,
            'auth' => 'google_auth' // authorize all requests
        ])->get($url);
    }

    private function getDomainName($url){
        return str_replace(array('http://','https://','www.'), '', $url);
    }
}
