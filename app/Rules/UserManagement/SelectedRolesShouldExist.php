<?php

namespace App\Rules\UserManagement;

use Illuminate\Contracts\Validation\Rule;

class SelectedRolesShouldExist implements Rule
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
        return in_array($value, ['Global Admin', 'Auditor', 'Contributor', 'Compliance Administrator', 'Policy Administrator', 'Risk Administrator', 'Third Party Risk Administrator']);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Selected role(s) doesn\'t exist.';
    }
}
