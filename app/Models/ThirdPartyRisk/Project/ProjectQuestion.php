<?php

namespace App\Models\ThirdPartyRisk\Project;

use App\Models\ThirdPartyRisk\Domain;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectQuestion extends Model
{
    use HasFactory;

    protected $table = 'third_party_project_questions';

    protected $fillable = ['questionnaire_id','question_id','text','domain_id'];

    public function single_answer()
    {
        return $this->hasOne(ProjectQuestionAnswer::class,'question_id');
    }

    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_id');
    }
}
