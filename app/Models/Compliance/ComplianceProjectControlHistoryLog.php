<?php

namespace App\Models\Compliance;

use Illuminate\Database\Eloquent\Model;
use App\Models\Compliance\ProjectControl;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ComplianceProjectControlHistoryLog extends Model
{
    use HasFactory;
    protected $table = 'compliance_project_controls_history_log';
    protected $fillable = [
        'project_id',
        'control_id',
        'applicable',
        'log_date',
        'status',
        'deadline',
        'frequency',
        'control_created_date',
        'control_deleted_date',
    ];

    public function compliance_project_controls() 
    {
        return $this->belongsTo(ProjectControl::class, 'control_id')->withTrashed();
    }
}
