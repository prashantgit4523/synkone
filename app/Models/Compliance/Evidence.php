<?php

namespace App\Models\Compliance;

use App\Models\DataScope\DataScope;
use App\Casts\CustomCleanHtml;
use Database\Factories\EvidenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Evidence extends Model
{
    use HasFactory;

    protected $table = 'compliance_project_control_evidences';
    protected $fillable = ['project_control_id', 'name', 'path', 'type', 'text_evidence', 'status', 'deadline'];


    protected $casts = [
        'name' => CustomCleanHtml::class,
        'name2' => CustomCleanHtml::class,
        'text_evidence' => CustomCleanHtml::class,
        'text_evidence_name' => CustomCleanHtml::class
    ];


    public function justifications()
    {
        return $this->hasMany('App\Models\Compliance\Justification');
    }

    public function projectControl()
    {
        return $this->belongsTo('App\Models\Compliance\ProjectControl', 'project_control_id', 'id');
    }

    public function projectControlWithoutDataScope()
    {
        return $this->belongsTo('App\Models\Compliance\ProjectControl', 'project_control_id', 'id')->withoutGlobalScope(new DataScope);
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
        return EvidenceFactory::new();
    }
}
