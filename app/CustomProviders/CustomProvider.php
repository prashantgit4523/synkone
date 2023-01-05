<?php

namespace App\CustomProviders;

use Exception;
use Illuminate\Database\Eloquent\Model;
use App\Models\Integration\IntegrationProvider;
use App\Traits\Integration\IntegrationApiTrait;
use Illuminate\Support\Facades\Log;

class CustomProvider
{
    use IntegrationApiTrait;

    protected IntegrationProvider $provider;
    protected array $fields;

    public function __construct(string $provider, string $tokenUrl = null)
    {
        $this->provider = IntegrationProvider::firstWhere('name', $provider);
        $this->fields = $this->getFields($this->provider);
        if ($tokenUrl) {
            $this->checkTokenExpiration($tokenUrl);
        }
    }

    public function logException($e, $message = null) {
        if($message){
            Log::error($message);
        }
        Log::error($e->getMessage());
    }

    /**
     * @throws Exception
     */
    protected function connect(IntegrationProvider $provider, array $fields, array $others = []): void
    {
        $required_fields = json_decode($provider->required_fields, true);
        $columnFields = [];

        foreach ($required_fields['fields'] as $key => $field) {
            if (array_key_exists($field['name'], $fields)) {
                $validation = explode('|', $field['validator']);

                if (array_key_exists('actingAs', $required_fields['fields'][$key])) {
                    $columnFields[$required_fields['fields'][$key]['actingAs']] = $field['encrypted'] ? encrypt($fields[$field['name']]) : $fields[$field['name']];
                    continue;
                }

                $required_fields['fields'][$key]['value'] = in_array('url', $validation) ? $this->getBaseUrl($fields[$field['name']]) : $fields[$field['name']];

                if (array_key_exists('encrypted', $required_fields['fields'][$key]) && $required_fields['fields'][$key]['encrypted']) {
                    $required_fields['fields'][$key]['value'] = encrypt($required_fields['fields'][$key]['value']);
                }
            }
        }

        if (!$provider->update(array_merge(['required_fields' => $required_fields], $columnFields))) {
            throw new Exception;
        }
        $provider->integration()->update(['connected' => true]);

        if (!empty($others)) {
            if (!$provider->update($others)) {
                throw new Exception;
            }
        }
    }

    protected function getFields(IntegrationProvider $provider): array
    {
        if (!$provider->required_fields) {
            return [];
        }

        $fields = [];
        $required_fields = json_decode($provider->required_fields, true);

        foreach ($required_fields['fields'] as $field) {
            $fields[$field['name']] = array_key_exists('actingAs', $field) ? $provider[$field['actingAs']] : $field['value'];

            if (array_key_exists('encrypted', $field) && $field['encrypted'] && $fields[$field['name']]) {
                $fields[$field['name']] = decrypt($fields[$field['name']]);
            }
        }

        return $fields;
    }

    protected function emptyToNull($value): ?string
    {
        if (empty($value)) {
            return null;
        }
        return $value;
    }

    public function diffAssets(array $assets): array
    {
        $asset_ids = collect($assets)->pluck('asset_id')->toArray();
        return $this
            ->provider
            ->assets()
            ->whereNotIn('asset_id', $asset_ids)
            ->pluck('asset_id')
            ->toArray();
    }

    protected function getBaseUrl(string $url): string
    {
        return rtrim($url, "/");
    }

    public function disconnect(): void
    {
        $required_fields = null;
        if ($this->provider->required_fields) {
            $required_fields = json_decode($this->provider->required_fields, true);

            foreach ($required_fields['fields'] as $key => $field) {
                if (array_key_exists('value', $field)) {
                    $required_fields['fields'][$key]['value'] = '';
                }
            }
        }

        $this->provider->update(['required_fields' => $required_fields, 'accessToken' => null, 'refreshToken' => null]);
        $this->provider->integration()->update(['connected' => false]);
        $this->provider->assets()->delete();
    }

    public function getProvider(): ?Model
    {
        return $this->provider;
    }

    public function checkTokenExpiration($tokenUrl)
    {
        if (!empty($this->provider->refreshToken) && !empty($this->provider->accessToken)) {
            //checks for token expiration & refresh the token
            if ($this->validateToken($this->provider->tokenExpires)) {
                $this->refreshExpiredToken($this->provider->refreshToken, $this->provider, $tokenUrl);
                $this->provider->refresh();
            }
        }
    }
}