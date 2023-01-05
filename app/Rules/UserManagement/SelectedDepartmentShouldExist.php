<?php

namespace App\Rules\UserManagement;

use App\Models\Administration\OrganizationManagement\Department;
use App\Models\Administration\OrganizationManagement\Organization;
use Illuminate\Contracts\Validation\Rule;

class SelectedDepartmentShouldExist implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $organization = Organization::first();
        if ($organization) {
            if ($value != 0) {
                return Department::where('id', $value)->exists();
            } else {
                return true;
            }
        }
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Selected department doesn\'t exist.';
    }
}
