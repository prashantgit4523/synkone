<?php

namespace App\Traits;

trait HasSorting {
    public function sort($allowed_sorts, $query, $sorting_by = null, $sorting_type = null) {
        $sort_by = $sorting_by ?? request()->input('sort_by');
        $sort_type = $sorting_type ?? request()->input('sort_type');

        $sort = strtolower($sort_type) === 'asc' ? 'ASC' : 'DESC';

        if(in_array($sort_by, $allowed_sorts) || array_key_exists($sort_by, $allowed_sorts)){
            $sort_by = array_key_exists($sort_by, $allowed_sorts) ? $allowed_sorts[$sort_by] : $sort_by;
            $query->orderBy($sort_by, $sort);
        }
    }
}