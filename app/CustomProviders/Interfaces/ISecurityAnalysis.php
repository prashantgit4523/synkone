<?php

namespace App\CustomProviders\Interfaces;

interface ISecurityAnalysis
{
    public function getTechnicalVulnerabilitiesScanStatus(): ?string;



}
