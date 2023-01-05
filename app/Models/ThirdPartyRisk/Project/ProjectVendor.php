<?php

namespace App\Models\ThirdPartyRisk\Project;

use App\Models\DataScope\BaseModel;
use App\Models\ThirdPartyRisk\Project;
use App\Models\ThirdPartyRisk\Vendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProjectVendor extends BaseModel
{
    use HasFactory;

    protected $table = 'third_party_project_vendors';

    protected $fillable = ['project_id','vendor_id','name', 'contact_name', 'email', 'status', 'country', 'industry_id', 'score'];
    protected $appends = ['level'];

    public function projects()
    {
        return $this->hasMany(Project::class,'vendor_id','id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function projectWithTrashed()
    {
        return $this->belongsTo(Project::class, 'project_id')->withTrashed();
    }

    public function latestProject()
    {
        return $this->hasOne(Project::class, 'id', 'project_id')->latestOfMany();
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function vendorWithTrashed()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id')->withTrashed();
    }

    public function getLevelAttribute()
    {
        $score = $this->score;
        if($score < 21)
        {
            $level = 1;
        }
        else if ($score >= 21 && $score < 41)
        {
            $level = 2;
        }
        else if ($score >= 41 && $score < 61)
        {
            $level = 3;
        }
        else if ($score >= 61 && $score < 81)
        {
            $level = 4;
        }
        else {
            $level  = 5;
        }

        return $level;
    }
}