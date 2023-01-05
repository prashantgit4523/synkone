<?php

namespace App\Utils;

use Auth;
use App\Nova\Model\Domain;
use App\Nova\Model\Tenant;
use Spatie\Permission\Models\Role;
use App\Models\Administration\OrganizationManagement\Department;
use App\Models\Administration\OrganizationManagement\Organization;
use App\Models\Compliance\ProjectControl;
use App\Models\Compliance\Standard;
use App\Models\RiskManagement\RiskMatrix\RiskMatrixAcceptableScore;
use App\Models\RiskManagement\RiskRegister;
use App\Models\Tasks\TasksSubmitToApproverAllowedStatus;
use App\Models\UserManagement\Admin;
use App\Models\UserManagement\AdminDepartment;
use Illuminate\Support\Facades\Storage;
use App\Models\Tasks\TasksEvidenceUploadAllowedStatus;
use App\Models\GlobalSettings\GlobalSetting;
use App\Models\RiskManagement\RiskNotification;

class RegularFunctions
{
    public static function set_db()
    {
        if (env('TENANCY_ENABLED')) {
            if (isset($_SERVER['HTTP_HOST'])) {
                $domain = Domain::where('domain', $_SERVER['HTTP_HOST'])->get();
                if ($domain->count() > 0) {
                    $tenant = Tenant::where('id', $domain[0]->tenant_id)->first();
                    if ($tenant) {
                        \Config::set('database.connections.mysql.database', 'tenant' . $tenant->id);
                        \DB::purge('mysql');
                        //  tenancy()->initialize($tenant);
                    }
                }
            }
        }
    }
    public static function unset_db()
    {
        if (env('TENANCY_ENABLED')) {
            \Config::set('database.connections.mysql.database', env('DB_DATABASE'));
            \DB::purge('mysql');
            //  tenancy()->initialize($tenant);
        }
    }
    public static function cleanXSS($input)
    {
        return htmlspecialchars($input, ENT_QUOTES, 'utf-8');
    }

    public static function getAllRoles()
    {
        self::set_db();
        $roles = Role::pluck('name');
        self::unset_db();

        return $roles;
    }

    public static function generateRandomPassword()
    {
        return substr(base_convert(sha1(uniqid(mt_rand())), 16, 36), 0, 10);
    }

    public static function getAllStandards()
    {
        self::set_db();

        $standards = Standard::all();
        self::unset_db();

        return $standards;
    }

    public static function getProjectStatus()
    {
        $status = ['Not Implemented', 'Under Review', 'Implemented'];

        return $status;
    }

    public static function getFrequency()
    {
        $frequency = ['One-Time', 'Monthly', 'Every 3 Months', 'Bi-Annually', 'Annually'];

        return $frequency;
    }

    public static function getContributorList()
    {
        self::set_db();
        $contributorArray = [];
        $contributors = Admin::all();
        foreach ($contributors as $contributor) {
            if ($contributor->hasRole('Contributor')) {
                $key = ucwords($contributor->full_name);
                $contributorArray[$key] = $contributor->id;
            }
        }
        self::unset_db();

        return $contributorArray;
    }

    public static function getControlContributorList()
    {
        self::set_db();
        $contributorArray = [];
        $contributors = Admin::where('status', 'active')->get();

        foreach ($contributors as $contributor) {
            if ($contributor->hasAnyRole(['Global Admin', 'Compliance Administrator', 'Contributor', 'Risk Administrator'])) {
                $key = ucwords($contributor->first_name . ' ' . $contributor->last_name);
                $contributorArray[$key] = $contributor->id;
            }
        }
        self::unset_db();

        return $contributorArray;
    }

    public static function getControlContributorListArray()
    {
        $contributorArray = [];
        $contributors = Admin::where('status', 'active')->get();

        foreach ($contributors as $contributor) {
            if ($contributor->hasAnyRole(['Global Admin', 'Compliance Administrator', 'Contributor'])) {
                $key = ucwords($contributor->first_name . ' ' . $contributor->last_name);
                $contributorArray[] = ["id" => $contributor->id, "name" => $key];
            }
        }

        return $contributorArray;
    }

    public static function notifyRiskContributorList(ProjectControl $projectControl, RiskRegister $riskRegister)
    {
        $riskAcceptableScore = RiskMatrixAcceptableScore::first();

        $riskRegister->status = 'Close';
        $riskRegister->residual_score = $riskAcceptableScore->score;
        $riskRegister->update();
        RiskNotification::firstOrCreate([
            'risk_id' => $riskRegister->id,
        ]);
    }

    public static function getRiskContributorList()
    {
        self::set_db();

        $contributorRiskArray = [];
        $contributors = Admin::where('status', 'active')->get();

        foreach ($contributors as $contributor) {
            if ($contributor->hasAnyRole(['Risk Administrator'])) {
                $key = ucwords($contributor->first_name . ' ' . $contributor->last_name);

                $contributorRiskArray[] = [
                    'name' => $key,
                    'email' => $contributor->email,
                ];
            }
        }
        self::unset_db();

        return $contributorRiskArray;
    }

    public static function getResponsibleName($id)
    {
        self::set_db();
        $admin = Admin::findorfail($id);
        self::unset_db();

        return ucwords($admin->full_name);
    }

    public static function getApproverName($id)
    {
        self::set_db();
        $admin = Admin::findorfail($id);
        self::unset_db();

        return ucwords($admin->full_name);
    }

    public static function getUsername($id)
    {
        self::set_db();
        $admin = Admin::findorfail($id);
        self::unset_db();

        return ucwords($admin->full_name);
    }

    public static function getTodayDate()
    {
        $globalSettings = GlobalSetting::first();
        $nowDateTime = new \DateTime('now', new \DateTimeZone($globalSettings->timezone));

        return $nowDateTime->format('Y-m-d');
    }

    public static function saveImage($path, $image)
    {
        $file_path = Storage::put('public/' . $path, $image, 'public');

        return $file_path;
    }

    public static function getTimeAgo($time)
    {
        $time = strtotime($time);
        $time_difference = time() - $time;

        if ($time_difference < 1) {
            return '1 second ago';
        }
        $condition = [
            12 * 30 * 24 * 60 * 60 => 'year',
            30 * 24 * 60 * 60 => 'month',
            24 * 60 * 60 => 'day',
            60 * 60 => 'hour',
            60 => 'minute',
            1 => 'second',
        ];

        foreach ($condition as $secs => $str) {
            $d = $time_difference / $secs;

            if ($d >= 1) {
                $t = round($d);

                return $t . ' ' . $str . ($t > 1 ? 's' : '') . ' ago';
            }
        }
    }

    public static function setSessionLifetime($lifeTime)
    {
        \Config::set('sessions.lifetime', $lifeTime);
    }

    public static function accessDeniedResponse()
    {
        if (request()->ajax()) {
            return response()->json([
                'success' => false,
                'message' => 'Access Denied!!',
            ]);
        } else {
            return redirect()->back()->withErrors(['error', 'Access Denied!']);
        }
    }

    public static function isTaskEvidencesUploadAllowed($projectControlId)
    {
        self::set_db();
        $result = TasksEvidenceUploadAllowedStatus::firstOrCreate(
            ['project_control_id' => $projectControlId]
        );

        $result->refresh();
        self::unset_db();

        return $result->status;
    }

    public static function isSubmitToApproverAllowed($projectControlId)
    {
        self::set_db();
        $result = TasksSubmitToApproverAllowedStatus::firstOrCreate(
            ['project_control_id' => $projectControlId]
        );

        $result->refresh();
        self::unset_db();

        return $result->status;
    }

    public static function updateTaskEvidencesUploadAllowedStatus($projectControlId, $status)
    {
        self::set_db();
        $model = TasksEvidenceUploadAllowedStatus::where('project_control_id', $projectControlId)->first();

        if ($model) {
            $model->update(
                ['status' => $status]
            );
        }
        self::unset_db();

        return $model;
    }

    public static function nextReviewDate($projectControl)
    {
        $currentDeadline = strtotime($projectControl->deadline);
        $frequency = $projectControl->frequency;
        $newDeadline = false;

        // GETTING THE NEXT REVIEW DATE WHEN current_cycle Increment
        if ($projectControl->current_cycle > 1) {
            switch ($frequency) {
                case 'Monthly':
                    $newDeadline = date('Y-m-d', strtotime('+1 month', $currentDeadline));
                    break;
                case 'Every 3 Months':
                    $newDeadline = date('Y-m-d', strtotime('+3 month', $currentDeadline));
                    break;
                case 'Bi-Annually':
                    $newDeadline = date('Y-m-d', strtotime('+6 month', $currentDeadline));
                    break;
                case 'Annually':
                    $newDeadline = date('Y-m-d', strtotime('+1 years', $currentDeadline));
                    break;
            }
        }

        return $newDeadline;
    }

    /*
    * Determines the after login redirect path based on user roles
    */
    public static function getRoleBasedRedirectPath()
    {
        self::set_db();

        $authUser = Auth::guard('admin')->user();

        $roleBasedRedirectPath = route('compliance-dashboard'); // Default

        if ($authUser->hasRole('Auditor')) {
            $roleBasedRedirectPath = route('compliance.implemented-controls');
        }

        if ($authUser->hasRole('Policy Administrator')) {
            $roleBasedRedirectPath = route('policy-management.campaigns');
        }

        if ($authUser->hasRole('Risk Administrator')) {
            $roleBasedRedirectPath = route('risks.dashboard.index');
        }

        if ($authUser->hasAnyRole(['Compliance Administrator', 'Contributor'])) {
            $roleBasedRedirectPath = route('compliance-dashboard');
        }

        if ($authUser->hasRole('Global Admin')) {
            $roleBasedRedirectPath = route('compliance-dashboard');
        }

        if ($authUser->hasRole("Third Party Risk Administrator")) {
            $roleBasedRedirectPath = route('third-party-risk.dashboard');
        }
        self::unset_db();

        return $roleBasedRedirectPath;
    }

    /**
     *  get auth user Department with all child department under it
     */
    public static function getChildDepartments($departmentId)
    {
        self::set_db();

        $department = Department::query()
            ->where('id', $departmentId)
            ->first();

        if ($department) {
            $departments[] = $department->id;
            $departmentsArray = $department->departments->toArray();
            array_walk_recursive($departmentsArray, function ($item, $key) use (&$departments) {
                if ($key === 'id') {
                    $departments[] = $item;
                }
            });

            return $departments;
        }
        self::unset_db();
    }

    /**
     * Check if scopable object is within auth user department
     */
    public static function isAppScopeUserDepartment($scope)
    {
        self::set_db();
        if ($scope) {
            $organization = $scope->organization_id;
            $department = $scope->department_id ? $scope->department_id : 0;
            $authUserDepartment = AdminDepartment::where('admin_id', Auth::guard('admin')->user()->id)->first();
            $user_organization = $authUserDepartment->organization_id;
            $user_department = $authUserDepartment->department_id ? $authUserDepartment->department_id : 0;

            $scope_concat = $organization . '-' . $department;
            $user_scope_concat = $user_organization . '-' . $user_department;

            if ($scope_concat === $user_scope_concat) {
                self::unset_db();
                return true;
            }
        }
        self::unset_db();
        return false;
    }

    public static function compareProjectControlScopeWithUserScope($scope)
    {
        self::set_db();
        if ($scope) {
            $projectControlDepartment = $scope->department_id ? $scope->department_id : 0;
            $authUserDepartment = AdminDepartment::where('admin_id', Auth::guard('admin')->user()->id)->first();

            $userOrganization = $authUserDepartment->organization_id;
            $userDepartment = $authUserDepartment->department_id ? $authUserDepartment->department_id : 0;

            //if logged-in user is from top level organization
            if ($userOrganization == 1 && $userDepartment == 0) {
                self::unset_db();
                return true;
            }

            //if logged-in user isn't from top level
            self::unset_db();
            $childUserDepartments = RegularFunctions::getChildDepartments($userDepartment);
            //check if project department is in logged-in user department and its child departments
            if (in_array($projectControlDepartment, $childUserDepartments)) {
                self::unset_db();
                return true;
            }
        }
        self::unset_db();
        return false;
    }

    public static function convertScopeToString($scope)
    {
        $organizationId = $scope->organization_id;
        $departmentId = $scope->department_id;
        $organizationExists = Organization::where('id', $organizationId)->exists();
        $departmentExists = Department::where('id', $departmentId)->exists();
        if ($organizationExists) {
            if ($departmentExists) {
                return $organizationId . '-' . $departmentId;
            } else {
                return $organizationId . '-0';
            }
        } else {
            return null;
        }
    }
}

// RegularFunctions::_construct();
// RegularFunctions::_destruct();
