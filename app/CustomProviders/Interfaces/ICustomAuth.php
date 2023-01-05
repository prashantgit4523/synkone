<?php

namespace App\CustomProviders\Interfaces;
interface ICustomAuth
{
    public function attempt(array $fields): bool;
}