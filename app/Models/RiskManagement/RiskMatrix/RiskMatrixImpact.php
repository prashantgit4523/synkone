<?php

namespace App\Models\RiskManagement\RiskMatrix;

use App\Casts\CustomCleanHtml;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RiskMatrixImpact extends Model
{
    use HasFactory;

    protected $table = 'risk_score_matrix_impacts';
    protected $fillable = ['name', 'index'];


    protected $casts = [
        'name'    => CustomCleanHtml::class,
    ];
}
