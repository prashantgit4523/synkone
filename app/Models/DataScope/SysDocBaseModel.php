<?php

namespace App\Models\DataScope;

use App\Helpers\SystemGeneratedDocsHelpers;

class SysDocBaseModel extends BaseModel {

    protected static function booted(){
        parent::booted();

        self::created(function () {
            SystemGeneratedDocsHelpers::checkSystemGeneratedDocuments();
        });

        self::deleted(function () {
            SystemGeneratedDocsHelpers::checkSystemGeneratedDocuments();
        });
    }
}