<?php

namespace App\Models\DataScope;

use App\Models\Administration\OrganizationManagement\Department;
use App\Models\Administration\OrganizationManagement\Organization;
use Auth;
use Illuminate\Database\Eloquent\Model;
use App\Helpers\DataScope\DataScopeHelpers;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BaseModel extends Model
{
    use HasFactory;

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected static function booted()
    {
        // while retreiving list add department and organization scope
        static::addGlobalScope(new DataScope);

        /**
         * while creating create morphedByMany Manually
         * eg. $this->morphedByMany(RiskRegister::class, 'scopable')->where('department_id',$this->id);
         */
        self::created(function ($model) {
            $current_data_scope = DataScopeHelpers::getCurrentDataScope();
            if ($current_data_scope) {
                $organizationId = (int)$current_data_scope['organization_id'];
                $departmentId = (int)$current_data_scope['department_id'];

                Scopable::create([
                    'organization_id' => $organizationId,
                    'department_id' => $departmentId > 0 ? $departmentId : null,
                    'scopable_id' => $model->id,
                    'scopable_type' => get_class($model)
                ]);
            }
        });
    }

    /**
     * retreive scope data ( department_id , organaization_id )
     * used when retreiving data, check scope class App\Models\DataScope\DataScope
     */
    public function scope()
    {
        return $this->hasOne(Scopable::class, 'scopable_id')->where('scopable_type', get_class($this));
    }

    /**
     * tree select department data (own department and department array passed )
     * cookie is not used here, have to use request ( data_scope and departments (array))
     */
    public function scopeOfDepartment($query)
    {
        $authUser = Auth::guard('admin')->user();
        $dataScope = explode('-', request('data_scope'));
        $departmentId = $dataScope[1];
        if ($authUser->hasRole('Global Admin')) {

            if ($departmentId) {
                $all_departments = array_merge($_REQUEST['departments'], [$departmentId]);
                $query->whereHas('scope', function ($qur) use ($all_departments) {
                    $qur->whereIn('department_id', $all_departments);
                });
            } else {
                $query->whereHas('scope', function ($qur) {
                    $qur->whereNull('department_id')
                        ->orWhereIn('department_id', $_REQUEST['departments']);
                });
            }

        } else {
            $all_departments = array_merge($_REQUEST['departments'], [$departmentId]);
            // if scope is the organization one then include null deparment scopes
            if (request('data_scope') === '1-0') {
                $query->whereHas('scope', function ($qur) use ($all_departments) {
                    $qur->whereNull('department_id')
                        ->orWhereIn('department_id', $all_departments);
                });
            } else {
                $query->whereHas('scope', function ($qur) use ($all_departments) {
                    $qur->whereIn('department_id', $all_departments);
                });
            }

        }
    }

    public function scopes()
    {
        return $this->morphMany(Scopable::class, 'scopable');
    }

    public function scopeScoped($query, $organization, $department = null)
    {
        return $query->whereHas('scope', function ($q) use ($organization, $department) {
            $q->where('organization_id', $organization)->where('department_id', $department);
        });
    }

    public function getDepartmentTitleAttribute()
    {
        if (is_null($this->scope)) {
            return null;
        }

        if (is_null($this->scope->department_id)) {
            return Organization::first()->name;
        }

        return Department::find($this->scope->department_id)?->name;
    }

}
