<?php

namespace App\CustomProviders\Interfaces;

interface ITicketing
{
    public function getMfaStatus(): ?string;

}
