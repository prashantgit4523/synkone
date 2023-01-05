<?php

namespace App\Http\Controllers\Administration;

use App\Constants\Role;
use App\Constants\Validation;
use App\Traits\HasSorting;
use Auth;
use Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Utils\DepartmentTree;
use App\Utils\RegularFunctions;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Models\UserManagement\Admin;
use Illuminate\Support\Facades\Hash;
use App\Models\UserManagement\LdapUser;
use App\Rules\Admin\Auth\StrongPassword;
use App\Models\Compliance\ProjectControl;
use App\Models\UserManagement\VerifyUser;
use App\Models\GlobalSettings\LdapSetting;
use App\Models\UserManagement\AdminDepartment;
use App\Notifications\CreateNewUserNotification;
use App\Notifications\CreateNewSsoUserNotification;
use App\Models\Administration\OrganizationManagement\Department;
use App\Models\Administration\OrganizationManagement\Organization;
use App\Models\Compliance\Project as ComplianceProject;
use App\Models\PolicyManagement\Campaign\Campaign;
use App\Models\RiskManagement\Project as RiskProject;
use App\Models\ThirdPartyRisk\Project as ThirdPartyRiskProject;
use App\Rules\UserManagement\GlobalAdminShouldBeSingleRole;
use App\Rules\UserManagement\HandleChangeOfRolesFromMaxToMin;
use App\Rules\UserManagement\OnlyTopLevelUsersCanBeGlobalAdmin;
use App\Rules\UserManagement\SelectedDepartmentShouldExist;
use App\Rules\UserManagement\SelectedRolesShouldExist;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class UserManagementController extends Controller
{
    use HasSorting;
    protected $loggedUser;
    protected $isGlobalAdmin;

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->loggedUser = Auth::guard('admin')->user();
            $this->isGlobalAdmin = $this->loggedUser ? $this->loggedUser->hasRole('Global Admin') : false;

            return $next($request);
        });
    }

    public function view()
    {
        return inertia('user-management/components/UserList');
    }

    private function getOwnerships()
    {
        return [
            [
                'table' => 'compliance_projects',
                'column' => 'admin_id'
            ],
            [
                'table' => 'control_documents',
                'column' => 'admin_id'
            ],
            [
                'table' => 'policy_campaigns',
                'column' => 'owner_id'
            ],
            [
                'table' => 'risk_projects',
                'column' => 'owner_id'
            ],
            [
                'table' => 'third_party_projects',
                'column' => 'owner_id'
            ],
            [
                'table' => 'compliance_project_control_justifications',
                'column' => 'creator_id'
            ],
            [
                'table' => 'risks_register',
                'column' => 'owner_id',
                'not_same_as' => 'custodian_id',
                'soft_deletes' => true
            ],
            [
                'table' => 'risks_register',
                'column' => 'custodian_id',
                'not_same_as' => 'owner_id',
                'soft_deletes' => true
            ],
            [
                'table' => 'compliance_project_controls',
                'column' => 'responsible',
                'not_same_as' => 'approver'
            ],
            [
                'table' => 'compliance_project_controls',
                'column' => 'approver',
                'not_same_as' => 'responsible'
            ]
        ];
    }

    public function create()
    {
        Log::info('User is attempting to create a new admin account');
        $admin = new Admin();
        $admin->roles = [];
        $roles = RegularFunctions::getAllRoles();
        $departmentTree = new DepartmentTree();
        $departmentTreeData = $departmentTree->getTreeData();

        return inertia('user-management/components/UserCreatePage', compact('roles', 'departmentTree', 'departmentTreeData'));
    }

    public function getLdapUserInfo(Request $request)
    {
        $ldapSetting = LdapSetting::first();
        if (is_null($ldapSetting)) {
            return response()->json([
                'success' => false,
            ]);
        }

        $ldapUser = LdapUser::where($ldapSetting->map_email_to, $request->email)->first();

        if (is_null($ldapUser)) {
            return response()->json([
                'success' => false,
            ]);
        }

        $ldapUserInfo = [
            'firstName' => $ldapUser[$ldapSetting->map_first_name_to] ? $ldapUser[$ldapSetting->map_first_name_to][0] : '',
            'lastName' => $ldapUser[$ldapSetting->map_last_name_to] ? $ldapUser[$ldapSetting->map_last_name_to][0] : '',
            'email' => $ldapUser[$ldapSetting->map_email_to] ? $ldapUser[$ldapSetting->map_email_to][0] : '',
            'contactNumber' => $ldapUser[$ldapSetting->map_contact_number_to] ? $ldapUser[$ldapSetting->map_contact_number_to][0] : '',
        ];

        return response()->json([
            'success' => true,
            'data' => $ldapUserInfo,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make(
            data: $request->all(),
            rules: [
                'auth_method' => 'required|in:Manual,SSO,LDAP',
                'first_name' => Validation::REQUIREDANDMAX,
                'last_name' => Validation::REQUIREDANDMAX,
                'department_id' => ['required', 'integer', new SelectedDepartmentShouldExist()],
                'email' => [
                    'required',
                    'email',
                    Rule::unique('admins')->withoutTrashed()
                ],
                'contact_number_country_code' => 'nullable',
                'contact_number' => ['nullable', 'numeric', 'digits_between:9,15', Rule::unique('admins')->withoutTrashed()],
                'roles' => ['required', 'array', new GlobalAdminShouldBeSingleRole()],
                'roles.*' => [
                    new SelectedRolesShouldExist(),
                    new OnlyTopLevelUsersCanBeGlobalAdmin(),
                ],
            ],
            customAttributes: ['email' => 'Email']
        )->stopOnFirstFailure(true);

        // Customizing the error message for nested role validation {roles.0}
        $validator->after(function ($validator) {
            foreach ($validator->errors()->messages() as $key => $value) {
                if (str_contains($key, 'roles.')) {
                    $validator->errors()->add('nested_roles', $value);
                }
            }
        });

        $validator->validate();

        $input = $request->toArray();
        $departmentId = $input['department_id'] > 0 ? $input['department_id'] : null;

        /* Making the status active when auth method is LDAP OR SSO */
        if ($input['auth_method'] == 'LDAP' || $input['auth_method'] == 'SSO') {
            $input['status'] = 'active';
        }
        unset($input['department_id']);
        $admin = Admin::create($input);

        $organization = Organization::first();

        /* Creating departments */
        $department = new AdminDepartment([
            'admin_id' => $admin->id,
            'organization_id' => $organization->id,
            'department_id' => $departmentId
        ]);
        $admin->department()->save($department);

        if ($request->roles) {
            $admin->assignRole($input['roles']);
        }

        if ($admin->auth_method == 'Manual') {
            // Creating email verification token
            VerifyUser::create([
                'user_id' => $admin->id,
                'token' => Str::random(100),
            ]);

            Notification::route('mail', $input['email'])->notify(new CreateNewUserNotification($admin));
        } else {
            //set sso user
            $admin->update(['is_manual_user' => 0]);
            Notification::route('mail', $input['email'])->notify(new CreateNewSsoUserNotification($admin));
        }
        Log::info('User has created a new admin account', [
            'admin_id' => $admin->id
        ]);

        return redirect(route('admin-user-management-view'))->with('success', 'User created successfully.');
    }

    public function edit(Admin $admin)
    {
        if ($this->loggedUser->id !== $admin->id && !$this->isGlobalAdmin) {
            abort(403);
        }
        $admin['created_date'] = date('d M, Y', strtotime($admin->created_at));
        $admin['updated_date'] = date('d M, Y', strtotime($admin->updated_at));
        $admin['last_login'] = isset($admin->last_login) ? date('d M, Y H:s A', strtotime($admin->last_login)) : null;
        $assignedRoles = [];
        foreach ($admin->roles as $role) {
            array_push($assignedRoles, $role->name);
        }
        $admin->roles = $assignedRoles;
        $departmentTree = new DepartmentTree();

        $updatedRolesArray = [
            [
                //for transfer of responsibilities
                'from' => [Role::GLOBAL_ADMIN, Role::CONTRIBUTOR],
                'to' => [Role::AUDITOR, Role::POLICY_ADMINISTRATOR, Role::RISK_ADMINISTRATOR, Role::THIRD_PARTY_ADMINISTRATOR]
            ],
            [
                //for ownership of compliance projects
                'from' => [Role::GLOBAL_ADMIN, Role::COMPLIANCE_ADMINISTRATOR],
                'to' => [Role::CONTRIBUTOR, Role::AUDITOR, Role::POLICY_ADMINISTRATOR, Role::RISK_ADMINISTRATOR, Role::THIRD_PARTY_ADMINISTRATOR]
            ],
            [
                //for ownership of risk projects
                'from' => [Role::GLOBAL_ADMIN, Role::RISK_ADMINISTRATOR],
                'to' => [Role::COMPLIANCE_ADMINISTRATOR, Role::CONTRIBUTOR, Role::AUDITOR, Role::POLICY_ADMINISTRATOR, Role::THIRD_PARTY_ADMINISTRATOR]
            ],
            [
                //for ownership of policy campaigns
                'from' => [Role::GLOBAL_ADMIN, Role::POLICY_ADMINISTRATOR],
                'to' => [Role::COMPLIANCE_ADMINISTRATOR, Role::CONTRIBUTOR, Role::AUDITOR, Role::RISK_ADMINISTRATOR, Role::THIRD_PARTY_ADMINISTRATOR]
            ],
            [
                //for ownership of third party risks
                'from' => [Role::GLOBAL_ADMIN, Role::THIRD_PARTY_ADMINISTRATOR],
                'to' => [Role::COMPLIANCE_ADMINISTRATOR, Role::CONTRIBUTOR, Role::AUDITOR, Role::RISK_ADMINISTRATOR, Role::POLICY_ADMINISTRATOR]
            ],
            [
                //for a custom special case
                'from' => [Role::COMPLIANCE_ADMINISTRATOR, Role::RISK_ADMINISTRATOR],
                'to' => [Role::CONTRIBUTOR, Role::AUDITOR, Role::POLICY_ADMINISTRATOR, Role::THIRD_PARTY_ADMINISTRATOR]
            ],
        ];

        return inertia('user-management/components/UserEditPage', [
            'admin' => $admin,
            'departmentId' => $admin->department->department_id ?? 0,
            'hasMFA' => $admin->hasTwoFactorEnabled(),
            'loggedInUser' => $this->loggedUser,
            'isGlobalAdmin' => $this->isGlobalAdmin,
            'roles' => RegularFunctions::getAllRoles(),
            'departmentTreeData' => $departmentTree->getTreeData(),
            'updatedRolesArray' => $updatedRolesArray,
        ]);
    }

    public function update(Request $request, Admin $admin)
    {
        if ($this->loggedUser->id !== $admin->id && !$this->isGlobalAdmin) {
            abort(403);
        }
        $validator = Validator::make(
            data: $request->all(),
            rules: [
                'first_name' => Validation::REQUIREDANDMAX,
                'last_name' => Validation::REQUIREDANDMAX,
                'department_id' => ['required', 'integer', new SelectedDepartmentShouldExist()],
                'email' => ['required', 'email', Rule::unique('admins', 'email')->ignore($admin->id)->withoutTrashed()],
                'contact_number_country_code' => 'nullable',
                'contact_number' => ['nullable', 'numeric', 'digits_between:9,15', Rule::unique('admins', 'contact_number')->ignore($admin->id)->withoutTrashed()],
                'roles' => [
                    Rule::requiredIf(function () {
                        return $this->isGlobalAdmin;
                    }),
                    new GlobalAdminShouldBeSingleRole(),
                    new HandleChangeOfRolesFromMaxToMin($admin)
                ],
                'roles.*' => [
                    new SelectedRolesShouldExist(),
                    new OnlyTopLevelUsersCanBeGlobalAdmin(),
                ],
            ],
            customAttributes: ['email' => 'Email']
        )->stopOnFirstFailure(true);

        // Customizing the error message for nested role validation {roles.0}
        $validator->after(function ($validator) {
            foreach ($validator->errors()->messages() as $key => $value) {
                if (str_contains($key, 'roles.')) {
                    $validator->errors()->add('nested_roles', $value);
                }
            }
        });

        $validator->validate();

        $input = $request->all();

        $departmentId = $input['department_id'] > 0 ? $input['department_id'] : null;

        $admin->fill($input)->save();

        /* Updating department */
        if ($admin->department === null) {
            $organization = Organization::first();
            $department = new AdminDepartment(['organization_id' => $organization->id, 'department_id' => $departmentId]);
            $admin->department()->save($department);
            if ($this->loggedUser->id !== $admin->id) {
                $admin->is_login = false;
                $admin->update();
            }
        } else {
            $admin->department()->update([
                'department_id' => $departmentId
            ]);
            if ($this->loggedUser->id !== $admin->id && $admin->department->department_id !== $departmentId) {
                $admin->is_login = false;
                $admin->update();
            }
        }

        if ($this->isGlobalAdmin) {
            if ($request->roles) {
                $admin->syncRoles($input['roles']);
            }
        }
        Log::info('User has updated an admin account', [
            'admin_id' => $admin->id
        ]);
        return back()->with('success', 'User profile updated successfully.');
    }

    public function checkAdminOwnership(Admin $admin)
    {
        return response()->json([
            'has_ownership' => $this->hasOwnerships($admin),
        ]);
    }

    public function delete(Admin $admin, Request $request)
    {
        $error_msg = "Something went wrong, please try again later.";

        if (in_array($admin->status, ['unverified', 'disabled'])) {
            if ($this->hasOwnerships($admin)) {
                // the admin should be transferred
                $target_id = $request->input('target_id');

                if (!$target_id || !$this->transferOwnerships($admin, $request->target_id)) {
                    return response()->json(['success' => false, 'message' => $error_msg]);
                }
            }

            $admin->delete();

            Log::info('User has deleted an admin account', [
                'admin_id' => $admin->id
            ]);

            return response()->json(['success' => true, 'message' => 'User deleted successfully.']);
        }

        return response()->json(['success' => false, 'message' => $error_msg]);
    }

    public function makeActive(Request $request, Admin $admin)
    {
        $admin->status = 'active';
        $admin->save();

        Log::info('User has activated an admin account', [
            'admin_id' => $admin->id
        ]);
        return response()->json(['sucess' => true, 'message' => 'User reactivated successfully!']);
    }

    public function makeDisable(Request $request, Admin $admin)
    {
        if ($this->loggedUser->id == $admin->id) {
            return RegularFunctions::accessDeniedResponse();
        }

        $admin->status = 'disabled';
        $admin->save();
        Log::info('User has disabled an admin account', [
            'admin_id' => $admin->id
        ]);
        return response()->json(['success' => true]);
    }

    public function globalAdminAvailability()
    {
        $totalGlobalAdmin = DB::table('model_has_roles')->select('role_id')->where('role_id', 1)->get()->count();

        return response()->json([
            'success' => true,
            'data' => $totalGlobalAdmin
        ]);
    }

    public function transferAssignments(Request $request, Admin $admin)
    {
        $request->validate([
            'transfer_to' => 'required|numeric',
        ], [], [
            'transfer_to' => 'User'
        ]);

        $transfer_to = $request->transfer_to;

        if (
            DB::table('admins')->where('id', $transfer_to)->exists()
            && $this->checkTransferability($admin, $transfer_to)
            && $this->transferOwnerships($admin, $transfer_to)
        ) {
            return response()->json(['success' => true, 'message' => 'Ownerships transferred successfully.']);
        }

        return response()->json(['success' => false, 'message' => 'Error transferring ownerships, please try again with a different user.']);
    }

    public function getUserProjectAssignments(Request $request, Admin $admin)
    {
        $should_be_transferred = false;
        $projects_count = ProjectControl::withoutGlobalScopes()->where('responsible', $admin->id)->orWhere('approver', $admin->id)->count();

        if ($projects_count || $this->hasOwnerships($admin)) {
            $should_be_transferred = true;
        }

        return response()->json(['should_be_transferred' => $should_be_transferred, 'data' => $projects_count]);
    }

    public function getAssignmentTransferableUsersWithDepartment(Request $request, Admin $admin)
    {
        $departmentId = $admin->department->department_id ?? 0;
        $organizationId = $admin->department->organization_id ?? 1;

        $childDepartmentIds = Department::where('parent_id', $departmentId)
            ->orWhere('id', $departmentId)
            ->pluck('id');

        // 1) all the users that have the same roles
        // 2) if the user to be disabled is a contributor, add compliance administrators
        // 3) add all the global admins

        $users = Admin::query()
            ->select('id', 'first_name', 'last_name', 'email')
            ->with('roles')
            ->where('status', 'active')
            ->where('id', '<>', $admin->id)
            ->whereHas('department', function ($query) use ($departmentId, $organizationId, $childDepartmentIds) {
                return ($departmentId == 0 && $organizationId != null) ?
                    $query->where('organization_id', $organizationId) :
                    $query->whereIn('department_id', $childDepartmentIds);
            })
            ->get()
            ->filter(function ($user) use ($admin) {
                // roles filtering
                return $user->hasRole('Global Admin')
                    || $user->hasAllRoles($admin->roles)
                    || ($admin->hasRole('Contributor') && $user->hasRole('Compliance Administrator'));
            })
            ->filter(function ($user) use ($admin) {
                // filter per transferability
                return $this->checkTransferability($admin, $user->id);
            })
            ->values()
            ->all();

        return response()->json(['success' => true, 'data' => $users]);
    }

    //updating password from user profile page
    public function updatePassword(Request $request, Admin $admin)
    {
        if ($this->loggedUser->id !== $admin->id && !$this->isGlobalAdmin) {
            abort(403);
        }
        if ($admin->auth_method != 'Manual') {
            exit;
        }

        $request->validate([
            'current_password' => [
                Rule::requiredIf(function () use ($admin) {
                    return $admin->id == $this->loggedUser->id;
                }),
                function ($attribute, $value, $fail) use ($admin) {
                    if (!Hash::check($value, $admin->password)) {
                        $fail('Current password is incorrect.');
                    }
                },
            ],
            'new_password' => ['required', 'confirmed', new StrongPassword()],
        ], [
            'new_password.required' => 'The new password field is required',
        ]);

        $admin->password = bcrypt($request->new_password);
        $admin->update();
        Log::info('User has changed the password for an admin account', [
            'admin_id' => $admin->id
        ]);

        return back()->with('success', 'Password successfully updated.');
    }

    public function resendEmailVerificationLink(Admin $admin)
    {
        $verifyUser = $admin->verifyUser;
        $verifyUser->token = Str::random(100);
        $verifyUser->update();

        Notification::route('mail', $admin->email)->notify(new CreateNewUserNotification($admin));

        Log::info('User has resent verification email to an admin account', [
            'admin_id' => $admin->id
        ]);

        return back()->with('success', 'User email verification link has been resent successfully.');
    }

    public function getJsonData(Request $request)
    {
        $start = $request->start;
        $length = $request->length;
        $draw = $request->draw;
        $keyword = $request->search['value'];

        $aminListQuery = Admin::when($request->search['value'] != null, function ($query) use ($keyword) {
            return $query->where(\DB::raw("CONCAT(`first_name`, ' ', `last_name`)"), 'LIKE', $keyword . '%')
                ->orWhere('email', 'LIKE', $keyword . '%')
                ->orWhere('contact_number', 'LIKE', $keyword . '%');
        });

        $count = $aminListQuery->count();
        $admins = $aminListQuery->offset($start)->take($length)->get();

        $render = [];

        foreach ($admins as $admin) {
            $assignedRoles = [];
            foreach ($admin->roles as $role) {
                array_push($assignedRoles, $role->name);
            }

            $roles = '';

            // user roles
            foreach ($assignedRoles as $assignedRole) {
                $roles .= "<span class='badge bg-soft-info text-info'> {$assignedRole}</span> ";
            }

            if ($admin->status == 'active') {
                $status = "<span class='badge bg-info'>Active</span>";
                $actionStatus = '';

                if ($this->loggedUser->id != $admin->id) {
                    $actionStatus = "<a class='dropdown-item disable-user' data-user-id='$admin->id' data-assignment-transferable-user-url='" . route('user.assignments-transferable-users', [$admin->id]) . "' data-user-project-assignments-url='" . route('user.project-assignments', [$admin->id]) . "' href='" . route('admin-user-management-make-disable', [$admin->id])
                        . "'
                        data-transfer-assignments-url='" . route('user.transfer-assignments', [$admin->id]) . "'>
                            <i class='mdi mdi-account-check me-2 text-muted font-18 vertical-middle'></i>Disable
                        </a>";
                }
            } elseif ($admin->status == 'unverified') {
                $status = "<span class='badge bg-warning'>Unverified</span>";
                $actionStatus = '';
            } else {
                $status = "<span class='badge bg-danger'>Disabled</span>";
                $actionStatus = "<a class='dropdown-item activate-user' href='" . route('admin-user-management-make-active', [$admin->id])
                    . "'><i class='mdi mdi-account-check me-2 text-muted font-18 vertical-middle'></i>Active</a>";
                $actionStatus .= "<a class='dropdown-item delete-user' href='" . route('admin-user-management-delete', [$admin->id])
                    . "'><i class='mdi mdi-delete-forever me-2 text-muted font-18 vertical-middle'></i>Delete</a>";
            }

            $action = "<div class='btn-group dropdown dropstart'>
                    <a href='javascript: void(0);' class='table-action-btn dropdown-toggle arrow-none btn btn-light btn-sm' data-toggle='dropdown'
                    aria-expanded='false'><i class='mdi mdi-dots-horizontal'></i></a><div class='dropdown-menu'>
                    <a class='dropdown-item' href='" . route('admin-user-management-edit', [$admin->id]) . "'><i class='mdi mdi-pencil me-2 text-muted font-18 vertical-middle'></i>Edit User</a>" .
                $actionStatus . '</div></div>';

            if ($admin->last_login) {
                $lastLogin = date('j M Y, H:i:s', strtotime($admin->last_login));
            } else {
                $lastLogin = '';
            }

            $organization = Organization::first();

            $department = "";

            if (!is_null($admin->department)) {
                /* admin departments table checking for departmen is set to top level*/
                if (is_null($admin->department->department_id)) {
                    $department = $organization ? $organization->name . ' (Organization)' : '';
                } else {
                    $department = $admin->department->department ? $admin->department->department->name : '';
                }
            }

            $render[] = [
                $admin->id,
                $admin->auth_method,
                $admin->first_name,
                $admin->last_name,
                $admin->email,
                $department,
                '(&nbsp;' . $admin->contact_number_country_code . '&nbsp;)&nbsp;' . $admin->contact_number,
                $roles,
                $status,
                date('j M Y', strtotime($admin->created_at)),
                date('j M Y', strtotime($admin->updated_at)),
                $lastLogin,
                $action,
            ];
        }

        $response = [
            'draw' => $draw,
            'recordsTotal' => $count,
            'recordsFiltered' => $count,
            'data' => $render,
        ];

        echo json_encode($response);
    }

    public function getUsersDataReact(Request $request)
    {
        $page = $request->page ?? 1;
        $size = $request->per_page ?? 10;
        $keyword = $request->search ?? null;

        $admins = Admin::query()
            ->leftJoin(DB::raw('admin_departments as admin_department'), 'admins.id', 'admin_department.admin_id')
            ->leftJoin(DB::raw('departments as department'), 'admin_department.department_id', 'department.id')
            ->select(['admins.*', 'department.name as department_name'])
            ->when($keyword, function ($query) use ($keyword) {
                return $query->where(\DB::raw("CONCAT(`first_name`, ' ', `last_name`)"), 'LIKE', $keyword . '%')
                    ->orWhere('email', 'LIKE', $keyword . '%')
                    ->orWhere('contact_number', 'LIKE', $keyword . '%');
            });

        $this->sort(['first_name', 'last_name', 'email', 'created_at', 'updated_at', 'status', 'last_login', 'auth_method', 'department_name'], $admins);

        $count = $admins->count();
        $admins = $admins->with(['roles', 'department'])->skip(--$page * $size)->take($size)->paginate($size);

        foreach ($admins as $admin) {
            foreach ($admin->roles as $role) {
                $admin['role_names'] = $admin->roles;
            }

            $organization = Organization::first();

            if (!is_null($admin->department)) {
                /* admin departments table checking for department is set to top level*/
                if (is_null($admin->department->department_id)) {
                    $admin['edited_department_name'] = $organization ? $organization->name . ' (Organization)' : '';
                } else {
                    $admin['edited_department_name'] = $admin->department->department ? $admin->department->department->name : '';
                }
            }

            $admin['created_date'] = date('d M, Y', strtotime($admin->created_at));
            $admin['updated_date'] = date('d M, Y', strtotime($admin->updated_at));
            $admin['last_login'] = isset($admin->last_login) ? date('d M, Y H:s A', strtotime($admin->last_login)) : null;
            $admin['first_name'] = ucwords($admin->first_name);
            $admin['last_name'] = ucwords($admin->last_name);
        }

        return response()->json([
            'data' => $admins,
            'total' => $count,
        ]);
    }

    public function disableUser($id)
    {
        $admin = Admin::findOrFail($id);

        $userHasProjectAssignments = ProjectControl::withoutGlobalScopes()
            ->where('responsible', $admin->id)
            ->orWhere('approver', $admin->id)
            ->count();

        if (!$this->isGlobalAdmin || $this->loggedUser->id == $admin->id || $userHasProjectAssignments) {
            return RegularFunctions::accessDeniedResponse();
        }

        $admin->status = 'disabled';
        $admin->is_login = false;
        $admin->save();

        return response()->json([
            'success' => true,
            'message' => 'User Disabled Successfully!'
        ]);
    }

    public function activateUser($id)
    {
        $admin = Admin::findOrFail($id);
        if ($this->loggedUser->id == $admin->id || !$this->isGlobalAdmin) {
            return RegularFunctions::accessDeniedResponse();
        }

        $admin->status = 'active';
        $admin->is_login = true;
        $admin->save();

        return response()->json([
            'success' => true,
            'message' => 'User Activated Successfully!'
        ]);
    }

    public function hasOwnerships(Model $admin)
    {
        foreach ($this->getOwnerships() as $ownership) {
            if (
                DB::table($ownership['table'])
                ->where($ownership['column'], $admin->id)
                ->when(array_key_exists('soft_deletes', $ownership) && $ownership['soft_deletes'], function ($q) {
                    $q->whereNull('deleted_at');
                })
                ->exists()
            ) {
                return true;
            }
        }

        return false;
    }

    private function checkTransferability(Model $admin, int $transfer_to): bool
    {
        foreach ($this->getOwnerships() as $ownership) {
            if (
                array_key_exists('not_same_as', $ownership)
                && DB::table($ownership['table'])
                ->where($ownership['not_same_as'], $transfer_to)
                ->where($ownership['column'], $admin->id)
                ->when(array_key_exists('soft_deletes', $ownership) && $ownership['soft_deletes'], function ($q) {
                    $q->whereNull('deleted_at');
                })
                ->exists()
            ) {
                return false;
            }
        }

        return true;
    }

    private function transferOwnerships(Model $admin, int $transfer_to)
    {
        try {
            DB::transaction(function () use ($transfer_to, $admin) {
                foreach ($this->getOwnerships() as $ownership) {
                    $results = DB::table($ownership['table'])
                        ->selectRaw(sprintf('`%s` as `owner_id`, id as `record_id`, \'%s\' as `column`, \'%s\' as `table`', $ownership['column'], $ownership['column'], $ownership['table']))
                        ->where($ownership['column'], $admin->id)
                        ->get()
                        ->map(function ($item) {
                            return get_object_vars($item);
                        })
                        ->toArray();

                    if (!count($results)) {
                        continue;
                    }

                    //change ownership
                    DB::table($ownership['table'])
                        ->where($ownership['column'], $admin->id)
                        ->update([$ownership['column'] => $transfer_to]);
                }
            });
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

    public function checkRoleUpdate()
    {
        $userId = request('userId');
        $oldUserRoles = request('oldUserRoles');
        $newUserRoles = request('newUserRoles');

        $showPopUp = false;

        $adminIsResponsibleOrApprover = ProjectControl::withoutGlobalScopes()->where('responsible', $userId)->orWhere('approver', $userId)->exists();
        $adminOwnsComplianceProject = ComplianceProject::withoutGlobalScopes()->where('admin_id', $userId)->exists();
        $adminOwnsPolicyCampaign = Campaign::withoutGlobalScopes()->where('owner_id', $userId)->exists();
        $adminOwnsRiskProject = RiskProject::withoutGlobalScopes()->where('owner_id', $userId)->exists();
        $adminOwnsThirdPartyRiskProject = ThirdPartyRiskProject::withoutGlobalScopes()->where('owner_id', $userId)->exists();

        $globalAdminIsInOldRole = in_array(Role::GLOBAL_ADMIN, $oldUserRoles);
        $complianceAdminIsInOldRole = in_array(Role::COMPLIANCE_ADMINISTRATOR, $oldUserRoles);
        $policyAdminIsInOldRole = in_array(Role::POLICY_ADMINISTRATOR, $oldUserRoles);
        $riskAdminIsInOldRole = in_array(Role::RISK_ADMINISTRATOR, $oldUserRoles);
        $thirdPartyRiskAdminIsInOldRole = in_array(Role::THIRD_PARTY_ADMINISTRATOR, $oldUserRoles);
        $contributorIsInOldRole = in_array(Role::CONTRIBUTOR, $oldUserRoles);

        $globalAdminIsInNewRole = in_array(Role::GLOBAL_ADMIN, $newUserRoles);
        $complianceAdminIsInNewRole = in_array(Role::COMPLIANCE_ADMINISTRATOR, $newUserRoles);
        $policyAdminIsInNewRole = in_array(Role::POLICY_ADMINISTRATOR, $newUserRoles);
        $riskAdminIsInNewRole = in_array(Role::RISK_ADMINISTRATOR, $newUserRoles);
        $thirdPartyRiskAdminIsInNewRole = in_array(Role::THIRD_PARTY_ADMINISTRATOR, $newUserRoles);
        $contributorIsInNewRole = in_array(Role::CONTRIBUTOR, $newUserRoles);

        //checking ownership for global admin
        if ($globalAdminIsInOldRole && !$globalAdminIsInNewRole) {
            //check all ownerships (compliance projects, risk projects, policy campaigns, third party risk projects and also check project assignments)
            if (
                ($adminIsResponsibleOrApprover && ($complianceAdminIsInNewRole || $contributorIsInNewRole)) ||
                ($adminOwnsComplianceProject && $complianceAdminIsInNewRole) ||
                ($adminOwnsPolicyCampaign && $policyAdminIsInNewRole) ||
                ($adminOwnsRiskProject && $riskAdminIsInNewRole) ||
                ($adminOwnsThirdPartyRiskProject && $thirdPartyRiskAdminIsInNewRole)
            ) {
                $showPopUp = false;
            } else {
                $showPopUp = true;
            }
        }
        //check for ownerships of compliance projects
        if ($complianceAdminIsInOldRole && !$complianceAdminIsInNewRole) {
            if (!$globalAdminIsInNewRole && !$contributorIsInNewRole) {
                $showPopUp = true;
            } else {
                if ($globalAdminIsInNewRole) {
                    $showPopUp = false;
                } else {
                    $showPopUp = $adminOwnsComplianceProject;
                }
            }
        }
        //check for ownerships of policy campaigns
        if ($policyAdminIsInOldRole && !$policyAdminIsInNewRole) {
            if (!$globalAdminIsInNewRole && !$complianceAdminIsInNewRole) {
                $showPopUp = true;
            } else {
                $showPopUp = $adminOwnsPolicyCampaign;
            }
        }
        //check for ownerships of risk projects
        if ($riskAdminIsInOldRole && !$riskAdminIsInNewRole) {
            if (!$globalAdminIsInNewRole && !$complianceAdminIsInNewRole) {
                $showPopUp = true;
            } else {
                $showPopUp = $adminOwnsRiskProject;
            }
        }
        //check for ownerships of third party risk projects
        if ($thirdPartyRiskAdminIsInOldRole && !$thirdPartyRiskAdminIsInNewRole) {
            if (!$globalAdminIsInNewRole && !$complianceAdminIsInNewRole) {
                $showPopUp = true;
            } else {
                $showPopUp = $adminOwnsThirdPartyRiskProject;
            }
        }
        //check for transfer of responsibilities
        if ($contributorIsInOldRole && !$contributorIsInNewRole) {
            if ($globalAdminIsInNewRole || $complianceAdminIsInNewRole) {
                $showPopUp = false;
            } else {
                $showPopUp = $adminIsResponsibleOrApprover;
            }
        }

        //if roles wasn't changed
        if ($oldUserRoles == $newUserRoles) {
            $showPopUp = false;
        }

        return $showPopUp;
    }
}
