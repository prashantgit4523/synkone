<?php

namespace App\Models\ThirdPartyRisk\Project;

use Illuminate\Database\Eloquent\Model;

class ProjectQuestionAnswer extends Model
{
    protected $table = 'third_party_project_question_answers';

    protected $fillable = ['project_id','question_id', 'answer'];
}
