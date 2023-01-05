<?php

namespace App\Traits;

use Auth;
use App\Models\Administration\OrganizationManagement\Department;

trait DataScopeAccessCheckTrait
{
    public function checkDataScopeAccess($model)
    {
        if (Auth::user()->hasRole('Global Admin')) {
            return true;
        }

        // get user department
        $auth_user_department = Auth::user()->department;

        // It is the top organization user if department id is null
        if (is_null($auth_user_department->department_id)) {
            return true;
        }

        // get scopable modal department
        $model_department = $model->department;

        // if modal department id is null it is from top organization, 
        // the user with department id is not from top organizaiton
        if (is_null($model_department->department_id) && $auth_user_department->department_id) {
            abort(404);
        }

        // if same department return 
        if ($model_department->department_id == $auth_user_department->department_id) {
            return true;
        }

        // check if of the child department
        $departments = Department::where('parent_id', $auth_user_department->department_id)->get();
        $check = $this->checkIfChildDepartmentData($departments, $model_department->department_id);
        if (!$check) {
            abort(404);
        }
    }

    /*
    * checking if department is under it
    */
    private function checkIfChildDepartmentData($departments, $model_department_id)
    {
        foreach ($departments as $department) {
            if ($department->id == $model_department_id) {
                return true;
            }
            if ($department->departments()->count() > 0) {
                $this->checkIfChildDepartmentData($department->departments, $model_department_id);
            }
        }
        return false;
    }
}
