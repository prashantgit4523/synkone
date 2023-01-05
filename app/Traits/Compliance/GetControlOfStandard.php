<?php

namespace App\Traits\Compliance;

use Illuminate\Support\Facades\DB;
use App\Models\Compliance\Standard;
use App\Models\Compliance\StandardControl;
use mysql_xdevapi\Exception;

trait GetControlOfStandard {

    /**
     * get control id from primary id , seperator and sub_id
     */
    public function getControlId($standard,$control_concated_id){
        $compliance_standard=null;
        match ($standard) {
            'isoiec_27001_22013' => $compliance_standard = Standard::select('id')->where('name', 'ISO/IEC 27001-2:2013')->first(),
            'uae_ia' => $compliance_standard = Standard::select('id')->where('name', 'UAE IA')->first(),
            'isr_v2' => $compliance_standard = Standard::select('id')->where('name', 'ISR V2')->first(),
            'sama_cyber_security_framework' => $compliance_standard = Standard::select('id')->where('name', 'SAMA Cyber Security Framework')->first(),
            'nca_ecc_12018' => $compliance_standard = Standard::select('id')->where('name', 'NCA ECC-1:2018')->first(),
            'nca_cscc_12019' => $compliance_standard = Standard::select('id')->where('name', 'NCA CSCC-1:2019')->first(),
            'soc_2' => $compliance_standard = Standard::select('id')->where('name', 'SOC 2')->first(),
            'pci_dss_321' => $compliance_standard = Standard::select('id')->where('name', 'PCI DSS 3.2.1')->first(),
            'gdpr' => $compliance_standard = Standard::select('id')->where('name', 'GDPR')->first(),
            'pci_dss_40' => $compliance_standard = Standard::select('id')->where('name', 'PCI DSS 4.0')->first(),
            'hipaa_security_rule' => $compliance_standard = Standard::select('id')->where('name', 'HIPAA Security Rule')->first(),
            'cis_critical_security_controls_group_1' => $compliance_standard = Standard::select('id')->where('name', 'CIS Critical Security Controls Group 1')->first(),
            'cis_critical_security_controls_group_2' => $compliance_standard = Standard::select('id')->where('name', 'CIS Critical Security Controls Group 2')->first(),
            'cis_critical_security_controls_group_3' => $compliance_standard = Standard::select('id')->where('name', 'CIS Critical Security Controls Group 3')->first(),
        };

        if($compliance_standard){
            // removing white space , line breaks from control_concated_id
            $control_concated_id= trim(preg_replace("/\r|\n|©/", "", $control_concated_id));
            $control=StandardControl::where([
                ['standard_id',$compliance_standard->id],
                [DB::raw("CONCAT(primary_id,id_separator,sub_id)"),$control_concated_id]
                ])
            ->first();
            if(!$control)
                echo 'Control not found in compliance standard : '.$compliance_standard->name.' with control id : '.$control_concated_id. PHP_EOL;
            return $control?->id;
        }
        else{
            echo 'Standard not found in compliance standard : '.$standard. PHP_EOL;
        }
    }
}