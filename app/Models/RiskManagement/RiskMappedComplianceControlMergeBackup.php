<?php

namespace App\Models\RiskManagement;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RiskMappedComplianceControlMergeBackup extends Model
{
    use HasFactory;

    protected $table = 'risks_mapped_compliance_controls_merge_backup';
    protected $primaryKey = 'pid';
    const CREATED_AT = 'backup_created_at';
    const UPDATED_AT = 'backup_updated_at';
}
