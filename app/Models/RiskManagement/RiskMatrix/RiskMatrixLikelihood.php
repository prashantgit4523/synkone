<?php

namespace App\Models\RiskManagement\RiskMatrix;

use App\Casts\CustomCleanHtml;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RiskMatrixLikelihood extends Model
{
    use HasFactory;

    protected $table = 'risk_score_matrix_likelihoods';
    protected $fillable = ['name', 'index'];

    protected $casts = [
        'name'    => CustomCleanHtml::class,
    ];

    /**
     * Get the scores for the likelihood.
     */
    public function scores()
    {
        return $this->hasMany(RiskMatrixScore::class, 'likelihood_index');
    }
}
