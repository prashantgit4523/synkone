<?php

namespace App\Models\Compliance;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectControlMergeBackup extends Model
{
    use HasFactory;

    protected $table = 'compliance_project_controls_merge_backup';
    protected $primaryKey = 'pid';
    const CREATED_AT = 'backup_created_at';
    const UPDATED_AT = 'backup_updated_at';
}
