<?php

namespace App\Helpers\DataScope\Exceptions;

use Exception;

class InvalidDataScopeFormat extends Exception
{
    public function __construct(string $data_scope)
    {
        parent::__construct(sprintf('Invalid data scope format. Using \'%s\'', $data_scope));
    }
}