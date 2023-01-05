<?php

namespace App\Models\Compliance;

use App\Casts\CustomCleanHtml;
use App\Models\DataScope\BaseModel;
use App\Models\DataScope\DataScope;
use App\Models\DataScope\Scopable;
use App\Models\DocumentAutomation\DocumentTemplate;
use App\Models\Integration\IntegrationControl;
use App\Models\Integration\IntegrationProvider;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectControl extends BaseModel
{
    use SoftDeletes;
    
    protected $table = 'compliance_project_controls';

    protected $fillable = [
        'project_id',
        'index',
        'applicable',
        'is_editable',
        'current_cycle',
        'name',
        'description',
        'required_evidence',
        'primary_id',
        'id_separator',
        'sub_id',
        'status',
        'amend_status',
        'responsible',
        'approver',
        'deadline',
        'frequency',
        'approved_at',
        'rejected_at',
        'automation',
        'document_template_id',
        'manual_override',
        'unlocked_at'
    ];

    protected $casts = [
        'name' => CustomCleanHtml::class,
        'description' => CustomCleanHtml::class,
        'required_evidence' => CustomCleanHtml::class,
        'primary_id' => CustomCleanHtml::class,
        'sub_id' => CustomCleanHtml::class,
    ];

    protected $appends = [
        'isEligibleForReview',
        'controlId',
        'idSeparators',
        'standardControlAutomation',
        'howToImplement',
        'self_data_scope',
        'isSgdControl'
    ];

    public function comments()
    {
        return $this->hasMany('App\Models\Compliance\Comment');
    }

    public function justifications()
    {
        return $this->hasMany('App\Models\Compliance\Justification');
    }

    public function controlHistory()
    {
        return $this->hasMany('App\Models\Compliance\ComplianceProjectControlHistoryLog', 'control_id');
    }

    public function project()
    {
        return $this->belongsTo('App\Models\Compliance\Project', 'project_id')->withoutGlobalScope(new DataScope);
    }

    public function responsibleUser()
    {
        return $this->belongsTo('App\Models\UserManagement\Admin', 'responsible');
    }

    public function approverUser()
    {
        return $this->belongsTo('App\Models\UserManagement\Admin', 'approver');
    }

    public function getSelfDataScopeAttribute(): ?string
    {
        if(!$this->scope){
            return null;
        }

        return $this->scope->organization_id . '-' . ($this->scope->department_id ?? '0');
    }

    /**
     * Method department
     *
     * @return void
     */
    public function department()
    {
        return $this->morphOne(Scopable::class, 'scopable');
    }

    public function evidencesUploadStatus()
    {
        return $this->hasOne('App\Models\Tasks\TasksEvidenceUploadAllowedStatus', 'project_control_id');
    }

    public function getAutomationMetaAttribute()
    {
        $priority = [32, 9, 7, 8, 10, 12, 43, 11, 13, 14, 17, 16, 18, 19, 2, 5, 21, 22, 36, 38, 35, 44];

        $integrationControl = IntegrationControl::where('standard_id', $this->project->standard_id)
            ->where('primary_id', $this->primary_id)
            ->where('sub_id', $this->sub_id)
            ->with(['integration_providers' => function ($query) {
                $query->whereHas('integration', function ($q) {
                    $q->where('connected', true);
                });
            }])->first();

        $action = $integrationControl ? $this->getActionName($integrationControl->action) : null;
        $provider = null;

        $set_on_this_priority = null;
        if ($integrationControl) {
            if (!$integrationControl->last_implemented_by) {
                foreach ($integrationControl->integration_providers as $integration_provider) {
                    $provider_priority = array_search($integration_provider->id, $priority);
                    if ($set_on_this_priority === null || $provider_priority < $set_on_this_priority) {
                        $provider = $integration_provider->integration->name;
                        $set_on_this_priority = $provider_priority;
                    }
                }
            } else {
                $provider = IntegrationProvider::find($integrationControl->last_implemented_by)->integration->name;
            }
        }

        return [
            'action' => $action,
            'provider' => $provider
        ];
    }

    private function getActionName($action_name)
    {
        $action_name = substr($action_name, 3);
        $action_name = preg_replace("([A-Z])", " $0", $action_name);
        return ucfirst(trim(strtolower($action_name)));
    }

    public function evidences()
    {
        if ($this->automation === 'document') {
            return $this->template->latest();
        } elseif ($this->automation === 'technical') {
            return $this->hasMany('App\Models\Compliance\Evidence')->whereIn('type', ['additional', 'json'])->orderByDesc('id');
        } else {
            return $this->hasMany('App\Models\Compliance\Evidence')->whereNotIn('type', ['additional', 'json'])->orderByDesc('id');
        }
    }

    public function risks()
    {
        return $this->belongsToMany('App\Models\RiskManagement\RiskRegister', 'risks_mapped_compliance_controls', 'control_id', 'risk_id')->withPivot('id', 'created_at', 'updated_at');
    }

    /**
     * get the status whether control is allowed to submit for review.
     */
    public function getIsEligibleForReviewAttribute()
    {
        if ($this->automation === 'none' && !is_null($this->evidences) && $this->evidences->count() > 0) {
            $evidences = $this->evidences()->get();

            if ($this->current_cycle > 1 && $this->unlocked_at) {
                $evidenceDocsCount = $evidences->where('updated_at', '>', $this->unlocked_at)->count();

                if ($evidenceDocsCount > 0) {
                    // Rejected control case
                    return $this->afterFirst($evidences);
                } else {
                    return false;
                }
            } else if (!in_array($this->amend_status, ["solved", 'submitted', 'requested_responsible', 'rejected']) && $this->amend_status != null) {
                $evidenceDocsUploadedAfterInitialApprovalCount = $evidences->where('updated_at', '>', $this->approved_at)
                    ->count();

                if ($evidenceDocsUploadedAfterInitialApprovalCount > 0 && $this->status != "Under Review") {
                    return true;
                }
            } else {
                // after first
                return $this->afterFirst($evidences);
            }
            // this to be removed after stable data, flaw => child control approved at was null while parent was implemented.
            if ($this->amend_status == 'requested_approver' && is_null($this->approved_at)) {
                return true;
            }
        } else {
            return false;
        }
    }

    public function getControlIdAttribute()
    {
        $controlId = null;

        if (!is_null($this->id_separator)) {
            $separatorId = ($this->id_separator == '&nbsp;') ? ' ' : $this->id_separator;

            $controlId = $this->primary_id . $separatorId . $this->sub_id;
        } else {
            $controlId = $this->primary_id . $this->sub_id;
        }

        return $controlId;
    }

    public function getIdSeparatorsAttribute()
    {
        return [
            '.' => 'Dot Separated',
            '&nbsp;' => 'Space Separated',
            '-' => 'Dash Separated',
            ',' => 'Comma Separated',
        ];
    }

    public function control_evidences() {
        return $this->hasMany(Evidence::class, 'project_control_id', 'id');
    }

    public function control_document() {
        return $this->template?->latest();
    }

    /**
     * Get the non breaking space if value is space.
     *
     * @return string
     */
    public function getIdSeparatorAttribute($value)
    {
        if ($value == ' ') {
            return '&nbsp;';
        }

        return $value;
    }

    /**
     * Set the description.
     *
     * @param string $value
     * @return void
     */
    public function setDescriptionAttribute($value)
    {
        $this->attributes['description'] = preg_replace('/_x([0-9a-fA-F]{4})_/', '', $value);
    }

    /**
     * Set the id_separator to space if value is non-breaking space.
     *
     * @param string $value
     *
     * @return void
     */
    public function setIdSeparatorAttribute($value)
    {
        if ($value == '&nbsp;') {
            $this->attributes['id_separator'] = ' ';
        } else {
            $this->attributes['id_separator'] = $value;
        }
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection $evidences
     * @return bool
     */
    public function afterFirst(\Illuminate\Database\Eloquent\Collection $evidences): bool
    {
        if ($this->status == 'Rejected' && !is_null($this->rejected_at)) {
            $evidenceDocsUploadedAfterRejectionCount = $evidences->where('updated_at', '>', $this->rejected_at)->count();

            return $evidenceDocsUploadedAfterRejectionCount > 0;
        } elseif ($this->status == 'Under Review' || $this->status == 'Implemented') {
            return false;
        } else {
            return true;
        }
    }

    public function template()
    {
        return $this->hasOne(DocumentTemplate::class, 'id', 'document_template_id');
    }

    public function getStandardControlAutomationAttribute()
    {
        $standardControl = StandardControl::where('standard_id', $this->project?->of_standard?->id)
            ->where('primary_id', $this->primary_id)
            ->where('sub_id', $this->sub_id)->first();

        if ($standardControl) {
            $result = $standardControl->automation;

            if ($result === 'document') {
                return !is_null($standardControl->document_template_id) ? $result : 'none';
            }
            return $standardControl->automation;
        }
        return false;
    }

    public function getIsSgdControlAttribute(){
        $is_generated= $this->template?->is_generated;
        if($is_generated){
            return true;
        }
        else{
            return false;
        }
    }

    // public function parentLinkedControl()
    // {
    //     $linkedControlId = $this->evidences()->where('type', 'control')->first()->path;
    //     return $linkedControlId ? ProjectControl::firstWhere('id', $linkedControlId) : null;
    // }

    public function childLinkedControls()
    {
        $childLinkedControlIds = Evidence::select('project_control_id')->where('type', 'control')->where('path', $this->id)->pluck('project_control_id');
        return ProjectControl::whereIn('id', $childLinkedControlIds)->get();
    }

    /**
     * @return null|string
     *
     * Getting how to implement link to the docs for technical automated  controls
     *
     */
    public function getHowToImplementAttribute(): ?string
    {
        return null;
    }
}
