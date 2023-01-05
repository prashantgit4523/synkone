<?php

namespace App\Console\Commands;

use App\Nova\Model\Tenant;
use Illuminate\Console\Command;

class TenantCustomComplianceFix extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant_compliance_fix:custom';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Custom command for compliance control fix.';

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
        $tenants=Tenant::all();
        foreach($tenants as $tenant){
            $this->info('Fixing tenant:'.$tenant->id);
            $cmd= 'cd ' . base_path() . ' && php artisan tenant:run compliance-controls:fix-and-merge --tenants=' .$tenant->id;
            $output=shell_exec($cmd);
            echo $output;
        }
        return 0;
    }
}
