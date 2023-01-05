<?php

namespace App\Models\RiskManagement;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RiskNotification extends Model
{
    use HasFactory;
    protected $table = 'risk_notifications';

    protected $fillable = [
        'risk_id',
        'risk_name',
        'project_control_id',
        'project_control_name',
        'treatment_options',
        'status',
        'receiver1_email',
        'receiver2_email',
        'project_owner_email',
        'project_name',
        'risk_project_name',
    ];
}
