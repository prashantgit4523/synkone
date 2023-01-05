<?php

namespace App\Helpers\DataScope\Exceptions;

use Exception;

class InvalidDataScopeDepartment extends Exception
{
    public function __construct(string $data_scope)
    {
        parent::__construct(sprintf('Invalid data scope department id. Using \'%s\'', $data_scope));
    }
}