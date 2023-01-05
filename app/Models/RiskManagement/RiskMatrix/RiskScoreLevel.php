<?php

namespace App\Models\RiskManagement\RiskMatrix;

use App\Casts\CustomCleanHtml;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RiskScoreLevel extends Model
{
    use HasFactory;

    protected $table = 'risk_score_matrix_levels';
    public $timestamps = false;

    protected $casts = [
        'name'    => CustomCleanHtml::class,
    ];

    /**
     * Get the levels level types.
     */
    public function levelTypes()
    {
        return $this->belongsTo(RiskScoreLevelType::class, 'level_type', 'level');
    }
}
