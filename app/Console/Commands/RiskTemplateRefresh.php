<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Traits\RisksManagement\RiskTemplateDataInfoTrait;
use App\Imports\RiskManagement\RiskTemplateImport;
use App\Models\RiskManagement\RisksTemplate;
use App\Models\RiskManagement\RiskStandard;
use Illuminate\Http\File;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RiskTemplateRefresh extends Command
{
    use RiskTemplateDataInfoTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'risk-template:refresh {--o|old}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command refreshes the risk template data';

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

        $this->refresh();

        return 0;
    }

    private function refresh()
    {
        if($this->option('old')){
            $riskStandards = $this->getRiskTemplateDataInfo();
            foreach($riskStandards as &$riskStandardFix){
                if($riskStandardFix['file_path'] === $this->standardFileBasePath.'UAE_IA.xlsx'){
                    $riskStandardFix['file_path'] = $this->standardFileBasePath.'UAE_IA_Old.xlsx';
                }

                if($riskStandardFix['file_path'] === $this->standardFileBasePath.'ISR_V2.xlsx'){
                    $riskStandardFix['file_path'] = $this->standardFileBasePath.'ISR_V2_Old.xlsx';
                }
            }
        } else {
            $riskStandards = $this->getRiskTemplateDataInfo();
        }

        foreach ($riskStandards as $key => $riskStandard) {
            DB::beginTransaction();

            try {
                $standard = RiskStandard::firstOrCreate([
                    'name' => $riskStandard['name'],
                ]);

                /* Deleting the existing risk items*/
                RisksTemplate::where('standard_id', $standard->id)->delete();

                $standardFile = new File($riskStandard['file_path']);

                $import = new RiskTemplateImport($standard->id);

                Excel::import($import, $standardFile);

                DB::commit();


                $logMsg = 'Risk(s) for `' . $standard->name . '`' . ' has been refreshed successfully!';
                $this->info($logMsg);
                Log::info($logMsg);
            } catch (\Exception $e) {
                DB::rollback();

                $logMsg = 'Risk(s) for  `' . $standard->name . '`' . ' not able to refresh!';
                $this->error($logMsg);
                Log::error($logMsg);
                Log::error($e->getMessage());
                Log::error($e->getFile());
                Log::error($e->getLine());
            }
        }
    }
}
