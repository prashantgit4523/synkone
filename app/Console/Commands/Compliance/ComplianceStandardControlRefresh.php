<?php

namespace App\Console\Commands\Compliance;

use Illuminate\Console\Command;
use App\Traits\Compliance\DefaultStandardsInfo;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Compliance\Standard;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ComplianceStandardControlRefresh extends Command
{
    use DefaultStandardsInfo;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'compliance-standards:refresh {--o|old}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command is responsible for refreshing the standard controls';

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
        $this->refreshStandardControls();

        return 0;
    }

    private function refreshStandardControls()
    {
        if($this->option('old')){
            $standards = $this->getDefaultStandards();
            foreach($standards as &$standard) {
               if($standard["controls_path"] === $this->standardBasePath.'UAE_IA-_DOT.xlsx'){
                   $standard["controls_path"] = $this->standardBasePath.'UAE_IA-_DOT_Old.xlsx';
               }

               if($standard["controls_path"] === $this->standardBasePath.'ISR V2-DOT.xlsx'){
                   $standard["controls_path"] = $this->standardBasePath.'ISR V2-DOT_Old.xlsx';
               }
            }
            unset($standard);
        } else {
            $standards = $this->getDefaultStandards();
        }

        foreach ($standards as $key => $standard) {
            $matchedStandard = Standard::where('name', $standard['name'])->where('version', $standard['version'])->with('projects')->first();

            if (is_null($matchedStandard)) {
                continue;
            }

            $controlSeparatorId = $standard['controls_seperator'];
            $excelData = Excel::toCollection(collect([]), base_path($standard['controls_path']))->first();
            $rows = [];
            foreach ($excelData as $key => $row) {
                $primaryId = (string)$row[0];
                $subId = (string)$row[1];
                $name = (string)$row[2];
                $description = (string)$row[3];

                if (empty($primaryId) || empty($subId) || empty($name) || empty($description)) {
                    continue;
                }
                
                $rows[] = [
                    'primary_id' => $primaryId,
                    'sub_id' => $subId,
                    'name' => $name,
                    'description' => $description,
                ];
            }
            unset($row);
            
            $duplicateControlRows = [];

            // Finding if standard csv has duplicate controls id
            foreach ($rows as $index => $row) {
                if ($index == 0) {
                    continue;
                }
                $targetPrimaryId = $row['primary_id'];
                $targetSubId = $row['sub_id'];



                foreach ($rows as $key => $rowItem) {
                    /* skipping the first iteration and also the control on check index */
                    if ($key == 0 || $key == $index ) {
                        continue;
                    }

                    if ($targetPrimaryId  === $rowItem['primary_id'] && $targetSubId === $rowItem['sub_id']) {
                        $duplicateControlRows [] = $index+1;
                    }
                }
            }
            unset($row);


            /* Skipping the update of current standard controls and projects control if csv file has duplicate entries */
            if (!empty($duplicateControlRows)) {
                $logMsg = 'Standard >> '.$matchedStandard->name.' '.$matchedStandard->version.' is skipped because its csv file has duplicate controlId(s) in row number '.implode(",", $duplicateControlRows);


                $this->error($logMsg);
                Log::error($logMsg);

                continue;
            }


            $controlsToCreate = [];

            foreach ($rows as $key => $row) {
                /*  */
                if ($key == 0) {
                    continue;
                }

                $primaryId = $row['primary_id'];
                $subId = $row['sub_id'];
                $controlName = $row['name'];
                $sanitizedControlName = clean($controlName);
                $controlDescription = clean($row['description']);

                /* Creating controls collection for creating records in */
                $controlsToCreate[] = [
                    'standard_id' => $matchedStandard->id,
                    'primary_id' => $primaryId,
                    'id_separator' => $controlSeparatorId,
                    'sub_id' => $subId,
                    'index' => $key,
                    'name' => $sanitizedControlName,
                    'slug' => Str::slug($controlName),
                    'description' => preg_replace('/_x([0-9a-fA-F]{4})_/', '', $controlDescription)
                ];
            }
            unset($row);

            DB::beginTransaction();

            try {
                /* Deleting the previously created controls */
                $matchedStandard->controls()->delete();

                /* creating controls */
                $matchedStandard->controls()->insert($controlsToCreate);


                DB::commit();

                $logMsg = 'Standard >> '.$matchedStandard->name.' '.$matchedStandard->version.' has been refreshed successfully!';

                $this->info($logMsg);
                Log::info($logMsg);
                // all good
            } catch (\Exception $e) {
                DB::rollback();

                $logMsg = 'Standard >> '.$matchedStandard->name.' '.$matchedStandard->version.' not able to refresh!';

                $this->error($logMsg);
                Log::error($logMsg);
                Log::error($e->getMessage());
            }
        }
        unset($standard);


        $this->newLine();
        $logMsg = 'Standard(s) controls are updated!';
        $this->info($logMsg);
    }
}