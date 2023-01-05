<?php
namespace App\CustomProviders;

use App\CustomProviders\Interfaces\ISecurityAnalysis;

class Tenable extends CustomProvider implements ISecurityAnalysis
{

    public function getTechnicalVulnerabilitiesScanStatus(): ?string
    {
        try{

        }catch(\Exception $e){
            writeLog('error', 'Tanable getTechnicalVulnerabilitiesScanStatus implementation failed: '. $e->getMessage());
        }
        return null;
    }

}
