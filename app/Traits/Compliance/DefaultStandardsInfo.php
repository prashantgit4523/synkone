<?php

namespace App\Traits\Compliance;

trait DefaultStandardsInfo {
    public $standardBasePath = 'database/seeders/Compliance/standards/updated/';


    public function getDefaultStandards()
    {
        return [
            [
                'name' => 'CIS Critical Security Controls Group 1',
                'version' => 'V7.1',
                'controls_path' => $this->standardBasePath.'CIS Critical Security Controls Group 1 v7.1-DOT.xlsx',
                'controls_seperator' => '.',
            ],

            [
                'name' => 'CIS Critical Security Controls Group 2',
                'version' => 'V7.1',
                'controls_path' => $this->standardBasePath.'CIS Critical Security Controls Group 2 v7.1-DOT.xlsx',
                'controls_seperator' => '.',
            ],

            [
                'name' => 'CIS Critical Security Controls Group 3',
                'version' => 'V7.1',
                'controls_path' => $this->standardBasePath.'CIS Critical Security Controls Group 3 v7.1-DOT.xlsx',
                'controls_seperator' => '.',
            ],

            [
                'name' => 'Cloud Computing Compliance Controls Catalogue',
                'version' => 'V9.0',
                'controls_path' => $this->standardBasePath.'Cloud Computing Compliance Controls Catalogue v9.2017-SPACE.xlsx',
                'controls_seperator' => '&nbsp;',
            ],

            [
                'name' => 'Cloud Security Alliance - CCM',
                'version' => 'V3.1',
                'controls_path' => $this->standardBasePath.'Cloud Security Alliance - CCM v3.1-SPACE.xlsx',
                'controls_seperator' => '&nbsp;',
            ],

            [
                'name' => 'HIPAA Security Rule',
                'version' => 'V1.0',
                'controls_path' => $this->standardBasePath.'HIPAA Security Rule v1.0 v2-DOT.xlsx',
                'controls_seperator' => '.',
            ],

            [
                'name' => 'Internet of Things Assessment Questionnaire',
                'version' => 'V3.0',
                'controls_path' => $this->standardBasePath.'Internet of Things Assessment Questionnaire v3.0-DOT.xlsx',
                'controls_seperator' => '.',
            ],

            [
                'name' => 'ISO/IEC 27001-2:2013',
                'version' => 'V1.0',
                'controls_path' => $this->standardBasePath.'ISO 27001-2 2013-DOT.xlsx',
                'controls_seperator' => '.',
            ],

            [
                'name' => 'ISO/IEC 27035-1:2016',
                'version' => 'V1.0',
                'controls_path' => $this->standardBasePath.'ISO 27035-1-DOT.xlsx',
                'controls_seperator' => '.',
            ],

            [
                'name' => 'NCA CSCC-1:2019',
                'version' => 'V1.0',
                'controls_path' => $this->standardBasePath.'NCA CSCC –1 2019-DASH.xlsx',
                'controls_seperator' => '-',
            ],

            [
                'name' => 'NCA ECC-1:2018',
                'version' => 'V1.0',
                'controls_path' => $this->standardBasePath.'NCA ECC – 1 2018-DASH.xlsx',
                'controls_seperator' => '-',
            ],

            //start
            // [
            //     'name' => 'NIST Cybersecurity Framework',
            //     'version' => 'V1.1',
            //     'controls_path' => $this->standardBasePath.'NIST Cybersecurity Framework v1.1-DOT.xlsx',
            //     'controls_seperator' => '.',
            // ],

            // [
            //     'name' => 'NIST SP 800-171 Appendix E',
            //     'version' => 'V1.0',
            //     'controls_path' => $this->standardBasePath.'NIST SP 800-171 Appendix E-DASH.xlsx',
            //     'controls_seperator' => '-',
            // ],
            // end

            [
                'name' => 'NIST SP 800-171 A',
                'version' => 'V1.0',
                'controls_path' => $this->standardBasePath.'NIST SP 800-171 A_-SPACE.xlsx',
                'controls_seperator' => '&nbsp;',
            ],

            [
                'name' => 'NIST SP 800-53 High-Impact Baseline',
                'version' => 'V4.0',
                'controls_path' => $this->standardBasePath.'NIST SP 800-53 High-Impact Baseline rev4-DASH.xlsx',
                'controls_seperator' => '-',
            ],

            // start
            // [
            //     'name' => 'NIST SP 800-53 Low-Impact Baseline',
            //     'version' => 'V4.0',
            //     'controls_path' => $this->standardBasePath.'NIST SP 800-53 Low-Impact Baseline rev4-DASH.xlsx',
            //     'controls_seperator' => '-',
            // ],

            // [
            //     'name' => 'NIST SP 800-53 Moderate-Impact Baseline',
            //     'version' => 'V4.0',
            //     'controls_path' => $this->standardBasePath.'NIST SP 800-53 Moderate-Impact Baseline rev4-DASH.xlsx',
            //     'controls_seperator' => '-',
            // ],
            // end

            [
                'name' => 'OWASP Level 1',
                'version' => 'V4.0',
                'controls_path' => $this->standardBasePath.'OWASP Level 1 v4.0-DOT.xlsx',
                'controls_seperator' => '.',
            ],

            [
                'name' => 'OWASP Level 2',
                'version' => 'V4.0',
                'controls_path' => $this->standardBasePath.'OWASP Level 2 v4.0-DOT.xlsx',
                'controls_seperator' => '.',
            ],

            [
                'name' => 'OWASP Level 3',
                'version' => 'V4.0',
                'controls_path' => $this->standardBasePath.'OWASP Level 3 v4.0-DOT.xlsx',
                'controls_seperator' => '.',
            ],

            [
                'name' => 'PCI DSS 3.2.1',
                'version' => 'V3.2.1',
                'controls_path' => $this->standardBasePath.'PCI DSS v3.2.1-DOT.xlsx',
                'controls_seperator' => '.',
            ],

            [
                'name' => 'Sarbanes Oxley Act',
                'version' => 'V7.0',
                'controls_path' => $this->standardBasePath.'Sarbanes Oxley Act v7.2002-DOT.xlsx',
                'controls_seperator' => '.',
            ],

            [
                'name' => 'SAMA Business Continuity Management Framework',
                'version' => 'V1.0',
                'controls_path' => $this->standardBasePath.'Sama BCMS Framework-DOT.xlsx',
                'controls_seperator' => '.',
            ],

            [
                'name' => 'SAMA Cyber Security Framework',
                'version' => 'V1.0',
                'controls_path' => $this->standardBasePath.'Sama Cybersecurity Framework-DOT.xlsx',
                'controls_seperator' => '.',
            ],

            [
                'name' => 'ISR V2',
                'version' => 'V2.0',
                'controls_path' => $this->standardBasePath.'ISR V2-DOT.xlsx',
                'controls_seperator' => '.',
            ],

            [
                'name' => 'ISO 22301:2019',
                'version' => 'V1.0',
                'controls_path' => $this->standardBasePath.'ISO 22301-2019-dot.xlsx',
                'controls_seperator' => '.',
            ],
            [
                'name' => 'AE/SCNS/NCEMA 7000:2021',
                'version' => 'V1.0',
                'controls_path' => $this->standardBasePath.'NCEMA 7000-2021-dot.xlsx',
                'controls_seperator' => '.',
            ],
            [
                'name' => 'UAE IA',
                'version' => 'V1.0',
                'controls_path' => $this->standardBasePath.'UAE_IA-_DOT.xlsx',
                'controls_seperator' => '.',
            ],

            [
                'name' => 'ISO/IEC 20000:2018',
                'version' => 'V1.0',
                'controls_path' => $this->standardBasePath.'ISO 20000-2018-dot.csv',
                'controls_seperator' => '.',
            ],
            [
                'name' => 'GDPR',
                'version' => 'V1.0',
                'controls_path' => $this->standardBasePath.'General Data Protection Regulation (GDPR)_v1.0.xlsx',
                'controls_seperator' => '.',
            ],
            [
                'name' => 'PCI DSS 4.0',
                'version' => 'V4.0',
                'controls_path' => $this->standardBasePath.'PCI DSS v4.0.xlsx',
                'controls_seperator' => '.',
            ],
        ];
    }

    //this old standards will no longer be in use, and we can delete them.
    public function getOldDefaultStandards(): array
    {
        return [
            [
                 'name' => 'PCI DSS - Self-Assessment Questionnaire A',
                 'version' => 'V3.2.1',
                 'controls_path' => $this->standardBasePath.'PCI DSS - Self-Assessment Questionnaire A v3.2.1-DOT.xlsx',
                 'controls_seperator' => '.',
            ],
            [
                'name' => 'PCI DSS - Self-Assessment Questionnaire A-EP',
                'version' => 'V3.2.1',
                'controls_path' => $this->standardBasePath.'PCI DSS - Self-Assessment Questionnaire A-EP v3.2.1-DOT.xlsx',
                'controls_seperator' => '.',
            ],

            [
                'name' => 'PCI DSS - Self-Assessment Questionnaire B',
                'version' => 'V3.2.1',
                'controls_path' => $this->standardBasePath.'PCI DSS - Self-Assessment Questionnaire B v3.2.1-DOT.xlsx',
                'controls_seperator' => '.',
            ],

            [
                'name' => 'PCI DSS - Self-Assessment Questionnaire B-IB',
                'version' => 'V3.2.1',
                'controls_path' => $this->standardBasePath.'PCI DSS - Self-Assessment Questionnaire B-IB v3.2.1-DOT.xlsx',
                'controls_seperator' => '.',
            ],

            [
                'name' => 'PCI DSS - Self-Assessment Questionnaire C',
                'version' => 'V3.2.1',
                'controls_path' => $this->standardBasePath.'PCI DSS - Self-Assessment Questionnaire C v3.2.1-DOT.xlsx',
                'controls_seperator' => '.',
            ],

            [
                'name' => 'PCI DSS - Self-Assessment Questionnaire C-VT',
                'version' => 'V3.2.1',
                'controls_path' => $this->standardBasePath.'PCI DSS - Self-Assessment Questionnaire C-VT v3.2.1-DOT.xlsx',
                'controls_seperator' => '.',
            ],

            [
                'name' => 'PCI DSS - Self-Assessment Questionnaire D Merchants',
                'version' => 'V3.2.1',
                'controls_path' => $this->standardBasePath.'PCI DSS - Self-Assessment Questionnaire D Merchants v3.2.1-DOT.xlsx',
                'controls_seperator' => '.',
            ],

            [
                'name' => 'PCI DSS - Self-Assessment Questionnaire D Service Providers',
                'version' => 'V3.2.1',
                'controls_path' => $this->standardBasePath.'PCI DSS - Self-Assessment Questionnaire D Service Providers v3.2.1-DOT.xlsx',
                'controls_seperator' => '.',
            ],

            [
                'name' => 'PCI DSS - Self-Assessment Questionnaire P2PE',
                'version' => 'V3.2.1',
                'controls_path' => $this->standardBasePath.'PCI DSS - Self-Assessment Questionnaire P2PE v3.2.1-DOT.xlsx',
                'controls_seperator' => '.',
            ],
            [
                'name' => 'PCI DSS Appendix A',
                'version' => 'V3.2.1',
                'controls_path' => $this->standardBasePath.'PCI DSS Appendix A v3.2.1-SPACE.xlsx',
                'controls_seperator' => '.',
            ],
            [
                'name' => 'HIPAA Privacy and Breach Rule',
                'version' => 'V1.0',
                'controls_path' => $this->standardBasePath.'HIPAA Privacy and Breach Rule v1.0 v2-DOT.xlsx',
                'controls_seperator' => '.',
            ],
        ];
    }
}
