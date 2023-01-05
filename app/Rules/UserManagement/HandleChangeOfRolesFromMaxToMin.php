<?php

namespace App\Rules\UserManagement;

use App\Models\Compliance\ProjectControl;
use Illuminate\Contracts\Validation\Rule;

class HandleChangeOfRolesFromMaxToMin implements Rule
{
    private $admin;
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($admin)
    {
        $this->admin = $admin;
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
        $maxRoles = ['Global Admin', 'Compliance Administrator', 'Contributor'];

        // Check if user had a max role
        $wasMaxRole = false;
        foreach ($maxRoles as $maxRole) {
            if (in_array($maxRole, $this->admin->roles()->pluck('name')->toArray())) {
                $wasMaxRole = true;
            }
        }

        // Check if user selected a min role and none of the  max roles
        $isMinRole = false;
        foreach ($maxRoles as $maxRole) {
            if (!in_array($maxRole, $value)) {
                $isMinRole = true;
            } else {
                $isMinRole = false;
                break;
            }
        }

        if ($wasMaxRole && $isMinRole) {
            // Check for project assignments
            $projectControls = ProjectControl::withoutGlobalScopes()
                ->where('responsible', $this->admin->id)
                ->orWhere('approver', $this->admin->id)
                ->exists();
            return !$projectControls;
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
        return 'User role update failed because the user has project assignments.';
    }
}
