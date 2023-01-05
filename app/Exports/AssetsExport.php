<?php

namespace App\Exports;

use App\Models\AssetManagement\Asset;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AssetsExport implements FromCollection, WithHeadings
{
    /**
    * @return \Illuminate\Support\Collection
    */
    public function collection()
    {
        return Asset::select(['name', 'description', 'type', 'owner', 'classification'])->get();
    }

    public function headings(): array
    {
        return [
            'name',
            'description',
            'type',
            'owner',
            'classification'
        ];
    }
}
