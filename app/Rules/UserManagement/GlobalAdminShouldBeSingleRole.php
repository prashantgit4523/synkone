<?php

namespace App\Rules\UserManagement;

use Illuminate\Contracts\Validation\Rule;

class GlobalAdminShouldBeSingleRole implements Rule
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
        if (in_array("Global Admin", $value)) {
            return (count($value) <= 1);
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
        return 'Global Admin should be a single role.';
    }
}
