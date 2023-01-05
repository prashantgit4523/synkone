<?php

namespace App\Traits\RisksManagement;


trait RiskTemplateDataInfoTrait{
    public $standardFileBasePath = 'database/seeders/RiskManagement/standards/';

    public function getRiskTemplateDataInfo()
    {
        return [
            [
                'name' => 'UAE IA',
                'file_path' => $this->standardFileBasePath.'UAE_IA.xlsx',
            ],
            [
                'name' => 'ISO/IEC 27002:2013',
                'file_path' => $this->standardFileBasePath.'ISO_27002.xlsx',
            ],
            [
                'name' => 'ISR V2',
                'file_path' => $this->standardFileBasePath.'ISR_V2.xlsx',
            ],
            [
                'name' => 'SAMA Cyber Security Framework',
                'file_path' => $this->standardFileBasePath.'Sama_Cybersecurity_Framework_DOT.xlsx',
            ],
            [
                'name' => 'NCA ECC-1:2018',
                'file_path' => $this->standardFileBasePath.'NCA_ECC_ 2018_DASH.xlsx',
            ],
            [
                'name' => 'NCA CSCC-1:2019',
                'file_path' => $this->standardFileBasePath.'NCA_CSCC_2019_DASH.xlsx',
            ]
        ];
    }
}