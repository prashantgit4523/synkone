<?php

namespace App\Models\RiskManagement;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RiskRegisterHistoryLog extends Model
{
    use HasFactory;
    protected $table = 'risk_register_history_log';
    protected $fillable = [
        'project_id',
        'risk_register_id',
        'category_id',
        'log_date',
        'risk_created_date',
        'risk_deleted_date',
        'status',
        'likelihood',
        'impact',
        'inherent_score',
        'residual_score',
        'is_complete',
    ];
}
