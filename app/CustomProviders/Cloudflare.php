<?php

namespace App\CustomProviders;

use App\CustomProviders\Interfaces\ICloudServices;
use App\CustomProviders\Interfaces\ICustomAuth;
use App\CustomProviders\Interfaces\IHaveHowToImplement;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class Cloudflare extends CustomProvider implements ICustomAuth, ICloudServices, IHaveHowToImplement
{

    private PendingRequest $client;

    public function __construct()
    {
        parent::__construct('cloudflare');
        $this->client = Http::baseUrl('https://api.cloudflare.com/client/v4');
    }

    public function attempt(array $fields): bool
    {
        $api_token = $fields['api_token'];

        $response = $this->client->withToken($api_token)->get('/user');

        if ($response->failed()) {
            return false;
        }

        $this->connect($this->provider, $fields);

        return true;
    }

    public function getWafStatus(): ?string
    {
        try {
            $response = Http::withToken(decrypt($this->provider->accessToken))
                ->get('https://api.cloudflare.com/client/v4/zones');

            if ($response->ok()) {
                $zone_ids = data_get($response, 'result.*.id');

                foreach ($zone_ids as $zone_id) {
                    try {
                        $response = Http::withToken(decrypt($this->provider->accessToken))
                            ->get("https://api.cloudflare.com/client/v4/zones/$zone_id/settings/waf");

                        if ($response->ok()) {
                            $body = json_decode($response->body(), true);
                            if ($body['result']['value'] === 'on') {
                                return json_encode($body);
                            }
                        }

                    } catch (\Exception $e) {
                        writeLog('error', 'CloudFlare getWafStatus implementation failed: '.$e->getMessage());
                    }
                }
            }
        } catch (\Exception $e) {
        }

        return null;
    }

    public static function getHowToImplementAction($action): ?string
    {
        $howToImplementActionsArr = [
            "getWafStatus" => "https://developers.cloudflare.com/waf/",
        ];

        return array_key_exists($action, $howToImplementActionsArr) ? $howToImplementActionsArr[$action] : null;
    }
}