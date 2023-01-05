<?php

namespace App\Models\ThirdPartyRisk\Project;

use App\Models\DataScope\BaseModel;
use App\Models\ThirdPartyRisk\Project;
use App\Models\ThirdPartyRisk\Questionnaire;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProjectQuestionnaire extends BaseModel
{
    use HasFactory;

    protected $table = 'third_party_project_questionnaires';

    protected $fillable = ['project_id','questionnaire_id','name', 'version', 'is_default'];

    public function questions()
    {
        return $this->hasMany(ProjectQuestion::class, 'questionnaire_id');
    }

    public function project(){
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function questionnaire()
    {
        return $this->belongsTo(Questionnaire::class, 'questionnaire_id');
    }
}
