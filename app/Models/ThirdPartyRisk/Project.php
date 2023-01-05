<?php

namespace App\Models\ThirdPartyRisk;

use App\Casts\CustomCleanHtml;
use App\Models\DataScope\BaseModel;
use App\Models\DataScope\Scopable;
use App\Models\DataScope\SysDocBaseModel;
use App\Models\ThirdPartyRisk\Project\ProjectQuestionnaire;
use App\Models\ThirdPartyRisk\Project\ProjectVendor;
use Database\Factories\ThirdPartyRisk\ProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends SysDocBaseModel
{
    use HasFactory, SoftDeletes;

    protected $table = 'third_party_projects';
    protected $appends = ['project_status'];
    protected $fillable = [
        'owner_id',
        'name',
        'questionnaire_id',
        'launch_date',
        'due_date',
        'timezone',
        'frequency',
        'vendor_id',
        'completed_date',
        'status'
    ];

    protected $casts = [
        'name'    => CustomCleanHtml::class,
        'launch_date'    => CustomCleanHtml::class,
        'due_date'    => CustomCleanHtml::class,
        'timezone'    => CustomCleanHtml::class,
        'frequency'    => CustomCleanHtml::class,
        'status'    => CustomCleanHtml::class,
    ];

    protected static function booted()
    {
        parent::booted();
        static::deleted(function ($project) {
            //Update the vendor score here
            $projectVendor = ProjectVendor::with(['projects' => function ($q) {
                $q->where('score', '!=', null);
            }])->where('project_id', $project->id)->first();

            //if project vendor exist
            if ($projectVendor) {
                $projectVendorIds = ProjectVendor::where('vendor_id', $projectVendor->vendor_id)->pluck('id');

                $vendor = Vendor::find($projectVendor->vendor_id);

                $totalVendorProjects = Project::select('id')
                    ->where('status', 'archived')
                    ->whereIn('vendor_id', $projectVendorIds->toArray())
                    ->count();
                $sumOfVendorScores = (int) Project::select('id', 'score')
                    ->where('status', 'archived')
                    ->whereIn('vendor_id', $projectVendorIds->toArray())
                    ->sum('score');
                //Avoid division by zero
                $averageVendorScore = $totalVendorProjects ? $sumOfVendorScores / $totalVendorProjects : 0;

                if ($vendor) {
                    $vendor->update(['score' => $averageVendorScore]);
                }
            }
        });
    }

    public function vendor()
    {
        return $this->belongsTo(ProjectVendor::class, 'vendor_id');
    }

    public function projectVendor()
    {
        return $this->belongsTo(ProjectVendor::class, 'vendor_id');
    }

    public function getProjectStatusAttribute()
    {
        /*
         * Completed: status is set to archived
         * When status is active, we have 3 possibilities
         * Overdue: We passed due date
         * Not started: We didn't reach launch_date yet
         * In Progress: We're between launch and due date
         * */

        $status = [
            'badge' => 'bg-success',
            'status' => 'Completed'
        ];

        if ($this->status === 'active') {
            if (now()->timezone($this->timezone)->betweenIncluded($this->launch_date, $this->due_date)) {
                $status = [
                    'badge' => 'bg-info',
                    'status' => 'In Progress'
                ];
            } else if (now()->timezone($this->timezone)->lessThan($this->launch_date)) {
                $status = [
                    'badge' => 'bg-danger',
                    'status' => 'Not Started'
                ];
            } else {
                $status = [
                    'badge' => 'bg-warning',
                    'status' => 'Overdue'
                ];
            }
        }

        return $status;
    }

    public function activities()
    {
        return $this->hasMany(ProjectActivity::class, 'project_id', 'id');
    }

    public function questionnaire()
    {
        return $this->belongsTo(ProjectQuestionnaire::class, 'questionnaire_id');
    }

    public function email()
    {
        return $this->hasOne(ProjectEmail::class);
    }

    protected static function newFactory()
    {
        return ProjectFactory::new();
    }

    /**
     * Get campaign owner.
     */
    public function owner()
    {
        return $this->belongsTo('App\Models\UserManagement\Admin', 'owner_id');
    }

    public function department()
    {
        return $this->morphOne(Scopable::class, 'scopable');
    }
}
