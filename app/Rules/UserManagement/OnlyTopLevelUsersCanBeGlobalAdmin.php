<?php

namespace App\Rules\UserManagement;

use Illuminate\Contracts\Validation\Rule;

class OnlyTopLevelUsersCanBeGlobalAdmin implements Rule
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
        if ($value == "Global Admin") {
            $departmentId = request('department_id');
            return $departmentId == 0;
        } else {
            return true;
        }
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Only the users from top department can be Global Admin.';
    }
}
