<?php

namespace App\Models\ThirdPartyRisk;

use App\Casts\CustomCleanHtml;
use App\Models\DataScope\BaseModel;
use Database\Factories\ThirdPartyRisk\QuestionnaireFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Questionnaire extends BaseModel
{
    use HasFactory;

    protected $table = 'third_party_questionnaires';
    protected $fillable = ['name', 'version', 'is_default'];

    protected $casts = [
        'name'    => CustomCleanHtml::class,
        'version'    => CustomCleanHtml::class,
    ];

    public function questions()
    {
        return $this->hasMany(Question::class, 'questionnaire_id');
    }

    protected static function newFactory()
    {
        return QuestionnaireFactory::new();
    }
}
