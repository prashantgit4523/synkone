<?php

namespace App\Models\DataScope;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScopableControlMergeBackup extends Model
{
    use HasFactory;

    protected $table = 'scopables_control_merge_backup';
    protected $primaryKey = 'pid';
    const CREATED_AT = 'backup_created_at';
    const UPDATED_AT = 'backup_updated_at';


}
