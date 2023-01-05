<?php

namespace App\Helpers\DataScope\Exceptions;

use Exception;

class InvalidDataScopeOrganization extends Exception
{
    public function __construct(string $data_scope)
    {
        parent::__construct(sprintf('Invalid data scope organization id. Using \'%s\'', $data_scope));
    }
}