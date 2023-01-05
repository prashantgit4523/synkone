<?php

namespace App\Imports\Compliance\Standard;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithConditionalSheets;
use App\Imports\Compliance\Standard\MapImports\IsoIec2013Map;

class ControlsMap implements WithMultipleSheets 
{
    use WithConditionalSheets;
   

    public function conditionalSheets(): array
    {
        return [
            new IsoIec2013Map()
        ];
    }
}
