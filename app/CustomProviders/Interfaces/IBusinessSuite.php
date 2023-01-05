<?php

namespace App\CustomProviders\Interfaces;

interface IBusinessSuite
{
    public function getEmailEncryptionStatus(): ?string;
}