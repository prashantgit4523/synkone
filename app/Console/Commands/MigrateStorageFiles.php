<?php

namespace App\Console\Commands;

use Dotenv\Dotenv;
use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use App\Models\Compliance\Evidence;
use Illuminate\Encryption\Encrypter;
use App\Models\PolicyManagement\Policy;
use Illuminate\Support\Facades\Storage;
use App\Models\GlobalSettings\GlobalSetting;
use App\Models\PolicyManagement\Campaign\CampaignPolicy;

class MigrateStorageFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrate:files';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate Files Between differents s3.';

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
        $encrypter = new Encrypter($this->parseKey(env('MIGRATION_APP_KEY')), config('app.cipher'));
        $bar = $this->output->createProgressBar(3);
        $bar->setMessage('Moving Compliance Evidences');
        $bar->setFormat("%message%\n %current%/%max% [%bar%] %percent:3s%%");
        $bar->start();

        $this->moveEvidenceFiles($encrypter);
        $bar->advance();

        $bar->setMessage('Moving Policy Module Files');
        $this->movePolicyModuleFiles();
        $bar->advance();

        $bar->setMessage('Moving Global Settings Files');
        $this->moveGlobalSettingsFiles();
        $bar->advance();

        $bar->finish();
        return 0;
    }


    /**
     * move evidence files
     */
    protected function moveEvidenceFiles($encrypter)
    {
        $evidences = Evidence::where('type', 'document')->get();
        foreach ($evidences as $evidence) {
            // getting the file and decrypting 
            $encryptedContents = Storage::get($evidence->path);
            $path = $evidence->path;
            if (tenant('id')) {
                $path = 'tenant' . tenant('id') . '/' . $path;
            }
            $decryptedContents = decrypt($encryptedContents);

            // encrypting with the key of server to be migrated and uploading it
            $encryptedContent = $encrypter->encrypt($decryptedContents);
            Storage::disk('s3-migration')->put($path, $encryptedContent, 'private');
        }
    }

    /**
     * move policy module files
     */
    protected function movePolicyModuleFiles()
    {
        $policies = Policy::where('type', 'document')->get();
        foreach ($policies as $policy) {
            // getting the file and uploading 
            $policy_file = Storage::get('public/' . $policy->path);
            $path = 'public/' . $policy->path;
            if (tenant('id')) {
                $path = 'tenant' . tenant('id') . '/' . $path;
            }

            Storage::disk('s3-migration')->put($path, $policy_file);
        }

        $campaignPolicies = DB::table('policy_campaign_policies')->where('type', 'document')->get();
        foreach ($campaignPolicies as $campaignPolicy) {
            // getting the file and decrypting 
            $campaign_policy_file = Storage::get('public/' . $campaignPolicy->path);
            $path = 'public/' . $campaignPolicy->path;
            if (tenant('id')) {
                $path = 'tenant' . tenant('id') . '/' . $path;
            }
            Storage::disk('s3-migration')->put($path, $campaign_policy_file);
        }
    }

    /**
     * move global settings files
     */
    protected function moveGlobalSettingsFiles()
    {
        $global_setting = DB::table('account_global_settings')->first();
        if (!str_contains($global_setting->company_logo, "assets/images/")) {
            $company_logo = Storage::disk('s3-public')->get($global_setting->company_logo);
            Storage::disk('s3-migration-public')->put($global_setting->company_logo, $company_logo);
        }
        if (!str_contains($global_setting->favicon, "assets/images/")) {
            $favicon = Storage::get('public/' . $global_setting->favicon);
            $path = 'public/' . $global_setting->favicon;
            if (tenant('id')) {
                $path = 'tenant' . tenant('id') . '/' . $path;
            }
            Storage::disk('s3-migration')->put($path, $favicon);
        }
    }

    /**
     * Parse the encryption key.
     *
     * @param  array  $config
     * @return string
     */
    protected function parseKey(string $config)
    {
        if (Str::startsWith($key = $config, $prefix = 'base64:')) {
            $key = base64_decode(Str::after($key, $prefix));
        }

        return $key;
    }
}
