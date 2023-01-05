<?php

namespace App\Traits\Integration;

trait serviceNowGetAssetsTrait
{
    private function assetReturn($asset, $service_data): array
    {
        return [
            'name' => $asset['display_name'],
            'description' => $service_data['short_description'],
            'type' => $asset['model_category'],
            'owner' => $asset['owned_by'] === '' ? 'Not Set' : $asset['owned_by'],
            'classification' => $service_data['busines_criticality'],
            'integration_provider_id' => $this->provider->id,
            'asset_id' => $asset['sys_id'],
        ];
    }
}
