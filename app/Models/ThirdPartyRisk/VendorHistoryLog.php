<?php

namespace App\Models\ThirdPartyRisk;

use App\Models\DataScope\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendorHistoryLog extends Model
{
    use HasFactory;

    protected $table = 'third_party_vendor_history_logs';
    protected $fillable = ['third_party_vendor_id', 'status', 'score', 'log_date', 'vendor_created_date', 'vendor_deleted_date', 'created_at', 'updated_at'];
    protected $appends = ['level'];

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

    public function projects()
    {
        return $this->hasMany(Project::class, 'vendor_id', 'third_party_vendor_id');
    }
}
