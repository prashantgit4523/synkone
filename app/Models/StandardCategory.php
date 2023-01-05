<?php

namespace App\Models;

use App\Models\Compliance\Standard;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StandardCategory extends Model
{
    use HasFactory;

    protected $table = 'compliance_standard_categories';

    const ISO_ID = 1;
    const FINANCIAL_ID = 2;
    const CLOUD_ID = 3;
    const HEALTHCARE_ID = 4;
    const KSA_ID = 5;
    const UAE_ID = 6;
    const APPLICATION_SECURITY_ID = 7;
    const INFRASTRUCTURE_SECURITY_ID = 8;
    const NIST_ID = 9;
    const ITSM_ID = 10;
    const PRIVACY_ID = 13;
    const OTHERS_ID = 11;
    const CUSTOM_ID = 12;

    public function standards()
    {
        return $this->hasMany(Standard::class,'category_id');
    }
}
