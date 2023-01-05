<?php

namespace App\Models\RiskManagement;

use App\Casts\CustomCleanHtml;
use Database\Factories\RiskRegisterFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\DataScope\BaseModel;
use App\Models\Compliance\ProjectControl;
use App\Models\UserManagement\Admin;
use App\Models\RiskManagement\RiskCategory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\RisksManagement\HelperMethodsTrait;
use App\Models\RiskManagement\RiskMatrix\RiskMatrixImpact;
use App\Models\RiskManagement\RiskMatrix\RiskMatrixLikelihood;
use Auth;

class RiskRegister extends BaseModel
{
    use SoftDeletes, HasFactory;
    use HelperMethodsTrait;

    protected $table = 'risks_register';
    protected $fillable = [
        'primary_id',
        'sub_id',
        'category_id',
        'project_id',
        'name',
        'risk_description',
        'affected_properties',
        'affected_functions_or_assets',
        'treatment',
        'treatment_options',
        'status',
        'owner_id',
        'custodian_id',
        'is_complete',
        'likelihood',
        'impact',
        'inherent_score',
        'residual_score'
    ];

    protected $casts = [
        'name'    => CustomCleanHtml::class,
        'risk_description'    => CustomCleanHtml::class,
        'treatment'    => CustomCleanHtml::class,
        'affected_functions_or_assets' => CustomCleanHtml::class,
        'affected_functions_or_assets'=> 'array',
    ];

    protected $appends=['ResidualRiskScoreLevel','InherentRiskScoreLevel', 'likelihood_name', 'impact_name'];

    public function category()
    {
        $model = $this->belongsTo(RiskCategory::class, 'category_id');

        return $model;
    }

    public function controls()
    {
        return $this->belongsToMany('App\Models\Compliance\ProjectControl', 'risks_mapped_compliance_controls', 'risk_id', 'control_id');
    }

    public function getLikelihoodNameAttribute()
    {
        $likelihood = RiskMatrixLikelihood::query()->where('index', $this->likelihood-1)->first();
        if($likelihood){
            return $likelihood->name;
        }
        return null;
    }

    public function getImpactNameAttribute()
    {
        $impact = RiskMatrixImpact::query()->where('index', $this->impact-1)->first();
        if($impact){
            return $impact->name;
        }
        return null;
    }

    public function riskMatrixLikelihood()
    {
       $this->likelihood=$this->likelihood -1;
       return $this->belongsTo(RiskMatrixLikelihood::class, 'likelihood', 'index');
    }

    public function riskMatrixImpact()
    {
        $this->impact=$this->impact -1;
        return $this->belongsTo(RiskMatrixImpact::class, 'impact', 'index');
    }

    /* Gets the risk matrix level matching inherent score*/
    public function getInherentRiskScoreLevelAttribute()
    {
        return $this->getRiskLevelByScore($this->inherent_score);
    }

    /* Gets the risk matrix level matching residual score*/
    public function getResidualRiskScoreLevelAttribute()
    {
        return $this->getRiskLevelByScore($this->residual_score);
    }

    /**
    * Get the comments for the blog post.
    */
    public function mappedControls()
    {
        return $this->belongsToMany(ProjectControl::class, 'risks_mapped_compliance_controls', 'risk_id', 'control_id');
    }

    public function getMappedComplianceControlAttribute()
    {
        $mappedControl = $this->mappedControls->first();

        $mappedComplianceControl = $mappedControl ? $mappedControl : null;

        return $mappedComplianceControl;
    }

    public function owner()
    {
        return $this->belongsTo(Admin::class, 'owner_id');
    }

    public function custodian()
    {
        return $this->belongsTo(Admin::class, 'custodian_id');
    }

    public function scopeOfDepartment($query)
    {
        $authUser=Auth::guard('admin')->user();
        $dataScope = explode('-', request('data_scope'));
        $departmentId = $dataScope[1];
        if($authUser->hasRole('Global Admin')){
           
            if($departmentId){
                $all_departments=array_merge(request()->get('departments'), [$departmentId]);
                $query->whereHas('scope', function ($qur) use ($all_departments) {
                    $qur->whereIn('department_id',$all_departments);
                });
            }
            else{
                $query->whereHas('scope', function ($qur) {
                    $qur->whereNull('department_id')
                            ->orWhereIn('department_id',request()->get('departments'));
                });
            }
            
        }
        else{
            $all_departments=array_merge(request()->get('departments'), [$departmentId]);
            // dd($all_departments);
            // dd($all_departments,$authUser->department->id,$_REQUEST['departments']);
            $query->whereHas('scope', function ($qur) use ($all_departments) {
                    $qur->whereIn('department_id',$all_departments);
            });
        }
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
        return RiskRegisterFactory::new();
    }

    public function project()
    {
        return $this->belongsTo('App\Models\RiskManagement\Project', 'project_id');
    }
}
