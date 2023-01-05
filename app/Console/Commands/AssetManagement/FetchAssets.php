<?php

namespace App\Console\Commands\AssetManagement;

use App\CustomProviders\Interfaces\IAssetProvider;
use App\Models\Integration\IntegrationCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Models\AssetManagement\Asset;
use App\Models\Integration\Integration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Client\RequestException;

class FetchAssets extends Command
{
    const CATEGORY_NAME = 'Asset Management and Helpdesk';
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'assets:fetch';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command fetches all the assets from the connected asset management providers and updates the db';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $integration = Integration::query()
            ->whereHas('category', function (Builder $query) {
                $query->where('name', self::CATEGORY_NAME);
            })
            ->firstWhere('connected', true);

        if ($integration) {
            $key = 'integrations.' . $integration->slug;
            if (config()->has($key)) {
                $class = config($key);
                $handler = new $class();

                if($handler instanceof IAssetProvider) {
                    try {
                        $assets = $handler->getAssets();

                        Asset::upsert($assets, ['asset_id'], ['name', 'description', 'type', 'classification', 'owner']);

                        $deleted_assets = $handler->diffAssets($assets);
                        $handler->getProvider()->assets()->whereIn('asset_id', $deleted_assets)->delete();

                    } catch (RequestException|\Throwable $e) {
                        Log::error('Something went wrong while fetching assets for ' . $integration->slug);
                        Log::error($e->getMessage());
                        $handler->disconnect();
                    }

                    // update the category updated_at
                    $this->touch();
                }
            }
        }
    }

    private function touch(): void
    {
        IntegrationCategory::firstWhere('name', self::CATEGORY_NAME)->touch();
    }
}
