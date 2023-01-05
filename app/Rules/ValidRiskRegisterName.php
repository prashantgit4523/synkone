<?php

namespace App\Rules;

use App\Models\RiskManagement\RiskRegister;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Database\Eloquent\Builder;


class ValidRiskRegisterName implements Rule
{
    private $ignoreId;
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($ignoreId = null)
    {
        $this->ignoreId = $ignoreId;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        return !RiskRegister::query()
            ->where('name', clean($value))
            ->when(request()->get('project_id'), function (Builder $query) {
                $query->where('project_id', '=',  request()->get('project_id'));
            })
            ->where('id', '<>', $this->ignoreId)
            ->exists();
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'The risk name has already been taken.';
    }
}
