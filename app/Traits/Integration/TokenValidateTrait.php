<?php

namespace App\Traits\Integration;

use GuzzleHttp\Client;
use App\Models\Integration\IntegrationProvider;
use Illuminate\Support\Facades\Log;

trait TokenValidateTrait
{

    function refreshExpiredToken($refreshToken, $provider, $token_url)
    {
        try {
            $httpClient = new Client();

            $response = $httpClient->post($token_url, [
                'headers' => ['Accept' => 'application/json'],
                'form_params' => [
                    'grant_type' => 'refresh_token',
                    'client_id' => config("services.{$provider->driver}.client_id"),
                    'client_secret' => config("services.{$provider->driver}.client_secret"),
                    'refresh_token' => $refreshToken
                ],
            ]);

            $this->updateToken(json_decode($response->getBody(), true), $provider->name);
        } catch (\Throwable $e) {
            Log::error(sprintf('Refreshing the token failed for %s:', $provider?->name));
            Log::error($e->getMessage());
            return false;
        }

        return true;
    }


    public function storeToken($response, $provider)
    {
        $provider = IntegrationProvider::where('name', $provider)->first();

        if ($provider) {
            $tokenData = [
                'accessToken' => $response->token,
                'refreshToken' => $response->refreshToken,
                'tokenExpires' => $response->expiresIn ? time() + $response->expiresIn : null
            ];

            if ($provider->name === 'azure-service-management' && isset($response->subscriptionId)) {
                $tokenData['subscriptionId'] = $response->subscriptionId;
            }

            $provider->update($tokenData);

            return true;
        }
        return false;
    }

    public function updateToken($response, $provider)
    {
        $provider = IntegrationProvider::where('name', $provider)->first();

        if ($provider) {
            $tokenData = [
                'accessToken' => $response['access_token'],
                'refreshToken' => $response['refresh_token'] ?? $provider->refreshToken,
                'tokenExpires' => $response['expires_in'] ? time() + $response['expires_in'] : null
            ];

            $provider->update($tokenData);

            return true;
        }
        return false;
    }

    function validateToken($expiryTime)
    {
        if (!is_null($expiryTime)) {
            return $expiryTime <= time();
        }
        return false;
    }
}
