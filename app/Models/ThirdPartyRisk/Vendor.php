<?php

namespace App\Models\ThirdPartyRisk;

use App\Casts\CustomCleanHtml;
use App\Models\DataScope\BaseModel;
use Database\Factories\ThirdPartyRisk\VendorFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $table = 'third_party_vendors';
    protected $fillable = ['name', 'contact_name', 'email', 'status', 'country', 'industry_id', 'score'];
    protected $appends = ['level'];

    protected $casts = [
        'name' => CustomCleanHtml::class,
        'contact_name' => CustomCleanHtml::class,
        'email' => CustomCleanHtml::class,
        'country' => CustomCleanHtml::class,
        'deleted_at' => 'datetime: Y-m-d H:i:s',
    ];

    public function industry()
    {
        return $this->belongsTo(Industry::class, 'industry_id');
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    public function latestProject()
    {
        return $this->hasOne(Project::class)->latestOfMany();
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

    protected static function newFactory()
    {
        return VendorFactory::new();
    }
}
