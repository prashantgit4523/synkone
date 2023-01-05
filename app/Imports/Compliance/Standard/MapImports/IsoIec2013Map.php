<?php

namespace App\Imports\Compliance\Standard\MapImports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use App\Traits\Compliance\GetControlOfStandard;
use App\Models\Compliance\ComplianceStandardControlsMap;

class IsoIec2013Map implements ToCollection, SkipsEmptyRows, WithHeadingRow, WithValidation, WithStartRow
{
    use GetControlOfStandard;
    /**
     * @return int
     */
    public function startRow(): int
    {
        return 3;
    }

    // iso control id should not be empty
    public function rules(): array
    {
        return [
            'isoiec_27001_22013' => [
                'required'
            ],
        ];
    }


    public function collection(Collection $rows)
    {
        $mapped_controls = [];
        foreach ($rows as $row) {
            // filtering the null values
            $row = $row->filter();
            // filtering the mapped control having none
            $row = $row->filter(function ($value) {
                return !str_contains($value, 'None');
            });
            // removing document automation column and iso control column
            unset($row['document_automation'], $row['technical_automation']);
            $mapped_control_ids = [];
            if (count($row) > 0) {
                foreach ($row as $standard => $map_id) {
                    // check if multiple eg.3.1.4.1.c.3|3.1.4.1.c.1
                    $multiple_controls = explode('|', $map_id);
                    if (count($multiple_controls) > 1) {
                        foreach ($multiple_controls as $controls) {
                            $mapped_control_id = $this->getControlId($standard, $controls);
                            if($mapped_control_id)
                                array_push($mapped_control_ids, $mapped_control_id);
                        }
                    } else {
                        $mapped_control_id = $this->getControlId($standard, $map_id);
                        if($mapped_control_id)
                            array_push($mapped_control_ids, $mapped_control_id);
                        
                    }
                }
                $mapped_control_ids=array_filter($mapped_control_ids);
                if (count($mapped_control_ids) > 1) {
                    // mapping multiple controls to each other
                    foreach ($mapped_control_ids as $key => $id) {
                        for ($i = 0; $i < count($mapped_control_ids); $i++) {
                            if ($key > $i) {
                                array_push($mapped_controls, ['control_id' => $mapped_control_ids[$i], 'linked_control_id' => $id]);
                            }
                        }
                    }
                }
            }
        }
        ComplianceStandardControlsMap::query()->delete();
        ComplianceStandardControlsMap::insert($mapped_controls);
    }
}
