<?php

namespace App\Models\ThirdPartyRisk;

use App\Casts\CustomCleanHtml;
use Database\Factories\ThirdPartyRisk\QuestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    use HasFactory;

    protected $table = 'third_party_questions';
    protected $fillable = ['text', 'questionnaire_id', 'domain_id'];

    protected $casts = [
        'text'    => CustomCleanHtml::class,
    ];

    public function questionnaire()
    {
        return $this->belongsTo(Questionnaire::class, 'questionnaire_id');
    }

    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_id');
    }

    public function answers()
    {
        return $this->hasMany(QuestionAnswer::class);
    }

    public function single_answer()
    {
        return $this->hasOne(QuestionAnswer::class);
    }

    protected static function newFactory()
    {
        return QuestionFactory::new();
    }
}
