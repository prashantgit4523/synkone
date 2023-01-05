<?php

namespace App\CustomProviders\Interfaces;
interface ICloudServices
{
    public function getWafStatus(): ?string;
}
