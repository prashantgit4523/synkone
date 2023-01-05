<?php

namespace App\Models\UserManagement;

use App\Casts\CustomCleanHtml;
use App\Models\OwnershipTransfer;
use App\Notifications\AdminResetPasswordNotification;
use DarkGhostHunter\Laraguard\Contracts\TwoFactorAuthenticatable;
use DarkGhostHunter\Laraguard\TwoFactorAuthentication;
use Database\Factories\AdminFactory;
use Illuminate\Auth\Authenticatable as AuthenticableTrait;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use App\Models\UserManagement\AdminDepartment;

class Admin extends Model implements Authenticatable, CanResetPasswordContract, TwoFactorAuthenticatable
{
    use AuthenticableTrait, HasFactory;
    use Notifiable;
    use CanResetPassword;
    use HasRoles;
    use TwoFactorAuthentication;
    use SoftDeletes;

    protected $fillable = ['auth_method', 'first_name', 'last_name', 'email', 'password', 'contact_number_country_code', 'contact_number', 'is_sso_auth', 'status', 'last_login', 'require_mfa', 'is_login', 'is_manual_user'];
    protected $appends = ['full_name', 'avatar', 'department_name'];
    protected $hidden = ['password', 'remember_token', 'session_id'];

    protected $casts = [
        'first_name' => CustomCleanHtml::class,
        'last_name' => CustomCleanHtml::class,
        'email' => CustomCleanHtml::class,
        'contact_number' => CustomCleanHtml::class
    ];

    public function sendPasswordResetNotification($token)
    {
        $this->notify(new AdminResetPasswordNotification($token));
    }

    public function verifyUser()
    {
        return $this->hasOne(VerifyUser::class, 'user_id');
    }

    public function getFullNameAttribute()
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getAvatarAttribute()
    {
        return strtoupper(mb_substr($this->first_name, 0, 1)) . '' . strtoupper(mb_substr($this->last_name, 0, 1));
    }

    public function getDepartmentNameAttribute()
    {
        return $this->department?->department?->name;
    }

    public function hasMfaRequired()
    {
        return $this->require_mfa;
    }

    public function department()
    {
        return $this->hasOne(AdminDepartment::class, 'admin_id');
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return \Illuminate\Database\Eloquent\Factories\Factory
     */
    protected static function newFactory()
    {
        return AdminFactory::new();
    }

    public function ownership_transfers()
    {
        return $this->hasMany(OwnershipTransfer::class, 'owner_id', 'id');
    }
}
