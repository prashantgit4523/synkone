<?php

namespace App\Helpers\DataScope;

use App\Helpers\DataScope\Exceptions\InvalidDataScopeDepartment;
use App\Helpers\DataScope\Exceptions\InvalidDataScopeFormat;
use App\Helpers\DataScope\Exceptions\InvalidDataScopeOrganization;
use App\Models\Administration\OrganizationManagement\Department;
use App\Models\Administration\OrganizationManagement\Organization;
use \Illuminate\Support\Facades\Cookie;
use Illuminate\Database\Eloquent\Model;

class DataScopeHelpers
{
    public const DATA_SCOPE_KEY = 'data_scope';

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws InvalidDataScopeFormat
     */
    public static function boot(): ?array
    {
//        if(!Cookie::has(self::DATA_SCOPE_KEY) && $data_scope){
//            self::setDataScopeCookie($data_scope['value']);
//        }

        return self::getCurrentDataScope();
    }

    /**
     * @throws InvalidDataScopeFormat
     */
    public static function setDataScopeCookie($value): ?array
    {
        cookie()->queue(cookie()->forever(self::DATA_SCOPE_KEY, $value));

        return self::dataScopeStringToArray($value);
    }

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     * @throws InvalidDataScopeFormat
     */
    public static function getCurrentDataScope()
    {
        if(request()->has('data_scope')){
            DataScopeHelpers::checkDataScope(request()->input('data_scope'));
            return DataScopeHelpers::dataScopeStringToArray(request()->input('data_scope'));
        }

//        if(Cookie::has(self::DATA_SCOPE_KEY)){
//            return self::dataScopeStringToArray(Cookie::get(self::DATA_SCOPE_KEY));
//        }

        // return the default logged in user data scope
        return self::getDefaultDataScope();
    }

    public static function getDefaultDataScope(Model $admin = null): ?array
    {
        if ($admin || auth('admin')->check()) {
            $user = $admin ?? auth('admin')->user();

            $department = $user?->department;

            $organization_id = $department?->organization_id;
            $department_id = $department?->department_id ?? 0;

            $data_scope = $organization_id . '-' . $department_id;

            return [
                'organization_id' => $organization_id,
                'department_id' => $department_id,
                'value' => $data_scope
            ];
        }

        return null;
    }

    /**
     * @throws InvalidDataScopeFormat
     */
    public static function dataScopeStringToArray(string $data_scope)
    {
        self::checkDataScopeFormat($data_scope);

        $arr_data_scope = explode('-', $data_scope);

        return [
            'organization_id' => $arr_data_scope[0],
            'department_id' => $arr_data_scope[1],
            'value' => $data_scope
        ];
    }

    /**
     * @throws InvalidDataScopeFormat
     */
    public static function checkDataScopeFormat(string $data_scope)
    {
        if(!preg_match("/^\d+-\d+$/", $data_scope)){
            throw new InvalidDataScopeFormat($data_scope);
        }
    }

    /**
     * @throws InvalidDataScopeFormat
     * @throws InvalidDataScopeOrganization
     * @throws InvalidDataScopeDepartment
     */
    public static function checkDataScope(string $data_scope)
    {
        self::checkDataScopeFormat($data_scope);

        $arr_data_scope = explode('-', $data_scope);

        if (Organization::where('id', $arr_data_scope[0])->doesntExist()) {
            throw new InvalidDataScopeOrganization($data_scope);
        }

        if (
            $arr_data_scope[1] !== '0'
            && Department::where('id', $arr_data_scope[1])->doesntExist()
        ) {
            throw new InvalidDataScopeDepartment($data_scope);
        }
    }
}