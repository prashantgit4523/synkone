<?php

namespace App\CustomProviders;

use App\CustomProviders\Interfaces\IHaveHowToImplement;
use App\Traits\Integration\BitBucketApiTrait;
use App\Traits\Integration\IntegrationApiTrait;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class Bitbucket extends CustomProvider implements Interfaces\IDevelopmentTools, IHaveHowToImplement
{
    // note: in bitbucket there are workspaces and inside them in repository
    // keys are optioned in settings inside repository or workspaces
    // branches are taken as main or master
    // some controls werent found in iso mapping and thus dont have controls mentioned below
    use IntegrationApiTrait;
    use BitBucketApiTrait;

    private $all_Repository;
    private $repositoryApi;
    public function __construct()
    {
        parent::__construct('bitbucket', 'https://bitbucket.org/site/oauth2/access_token');
        $this->repositoryApi = 'https://api.bitbucket.org/2.0/repositories/';
        $this->all_Repository = $this->getRepositories();
    }

    // standard:ISO 27001-2-2013,
    // control:7.5.3.b,
    // logic used:user has 2fa enabled,
    public function getMfaStatus(): ?string
    {
        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get('https://api.bitbucket.org/2.0/user');

                if ($response->ok()) {
                    $body = json_decode($response->body(), true);

                    $apiResponse = $body['value'] ?? $body;

                    $required_values = ["has_2fa_enabled" => true];
                    $additional_values = ['uuid','display_name'];
                    $filter_operator = '=';

                    return json_encode($this->formatResponse(
                        $apiResponse,
                        $required_values,
                        $additional_values,
                        $filter_operator
                    ));
                }
        } catch (\Exception $e) {
            writeLog('error', 'Bitbucket getMfaStatus implementation failed: '.$e->getMessage());
        }

        return null;
    }

    //  standard:ISO 27001-2-2013,
    // control:A.9.4.5,
    // logic used:user is a admin and has 2fa enabled,
    public function getAdminMfaStatus(): ?string
    {
        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get('https://api.bitbucket.org/2.0/user');

                if ($response->ok()) {
                    $body = json_decode($response->body(), true);

                    $apiResponse = $body['value'] ?? $body;

                    $required_values = ["has_2fa_enabled" => true, "is_admin" => true];
                    $additional_values = ['uuid','display_name'];
                    $filter_operator = '=';

                    return json_encode($this->formatResponse(
                        $apiResponse,
                        $required_values,
                        $additional_values,
                        $filter_operator
                    ));
                }
        } catch (\Exception $e) {
            writeLog('error', 'Bitbucket getAdminMfaStatus implementation failed: '.$e->getMessage());
        }

        return null;
    }

    // standard:ISO 27001-2-2013,
    // control:A.14.2.2,
    // logic used: has git commits,
    public function getGitStatus(): ?string
    {
        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get('https://api.bitbucket.org/2.0/user');

            if ($response->ok()) {
                $body = json_decode($response->body(), true);

                $username = $body['display_name'];
                $repositoriesUrl = $this->repositoryApi . $username;
                $getRepositoriesResponse = Http::withToken($this->provider->accessToken)
                    ->get($repositoriesUrl);
                $repositoriesArray = json_decode($getRepositoriesResponse->body(), true);

                if (count($repositoriesArray['values'])) {
                    foreach ($repositoriesArray['values'] as $repository) {
                        $repositorySlug = $repository['slug'];
                        $commitsUrl = $this->repositoryApi . $username . '/' . $repositorySlug . '/commits';
                        $getCommitsResponse = Http::withToken($this->provider->accessToken)
                            ->get($commitsUrl);
                        $commitsArray = json_decode($getCommitsResponse->body(), true);
                        if (count($commitsArray['values'])) {
                            foreach ($commitsArray['values'] as $commit) {
                                $data['dev_tool'] = 'Bitbucket';
                                $data['repo_name'] = $repository['name'];
                                $data['message'] = $commit['message'];
                                $data['committer_name'] = $commit['author']['user']['display_name'];
                                $data['committed_date'] = date('M d, Y \a\t g:h A', strtotime($commit['date']));
                                if (count($data)) {
                                    return json_encode($data);
                                }
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            writeLog('error', 'Bitbucket getGitStatus implementation failed: '.$e->getMessage());
            return null;
        }
        return null;
    }

    // standard:ISO 27001-2-2013,
    // control:A.9.9.2, A.9.2.5,
    // requirement: check all accounts from the service are unique. It should always be true,
    // logic used: all accounts are unique,
    // here same accounts couldn't be added
    public function getUniqueAccounts(): ?string
    {
        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get('https://api.bitbucket.org/2.0/user');
            if ($response->ok()) {
                return json_encode(['unique_accounts' => true]);
            }
        } catch (\Exception $e) {
            writeLog('error', 'Bitbucket getUniqueAccounts implementation failed: '.$e->getMessage());
            return null;
        }
        return null;
    }

    // standard:ISO 27001-2-2013,
    // control:A.9.2.1,
    // requirement:make sure the repository(ies) are not public.
    // logic used: atleast one private repository,
    public function getPrivateRepository(): ?string
    {
        try {
            foreach ($this->all_Repository as $single_repo) {
                $repository = $single_repo['repo'];
                $repository['project_name']=$repository['project']['name'];
                if ($repository['is_private'] === true) {
                        $apiResponse = $repository;
                        $required_values = ["is_private"=>true];
                        $additional_values = ['project_name','uuid','description','created_on'];
                        $filter_operator = '=';

                        return json_encode($this->formatResponse(
                            $apiResponse,
                            $required_values,
                            $additional_values,
                            $filter_operator
                        ));
                    }
                }
        } catch (\Exception $e) {
            writeLog('error', 'Bitbucket getPrivateRepository implementation failed: '.$e->getMessage());
            return null;
        }
        return null;
    }

    // standard:ISO 27001-2-2013,
    // control:A.9.2.6,
    // requirement:Check that inactive users are removed.
    // logic used: check if there is any inactive user,
    public function getInactiveUsersRemoval(): ?string
    {
        try {
            $workspace_list = Http::withToken($this->provider->accessToken)
                ->get('https://api.bitbucket.org/2.0/user/permissions/workspaces', [
                    'fields' => 'values.workspace.uuid',
                ]);

            if ($workspace_list->ok()) {
                $active_user = [];
                $workspace_list_body = json_decode($workspace_list->body(), true)['values'];
                foreach ($workspace_list_body as $workspace) {
                    $user_list_of_workspace = Http::withToken($this->provider->accessToken)
                        ->get('https://api.bitbucket.org/2.0/workspaces/' . $workspace['workspace']['uuid'] . '/permissions', [
                            'fields' => 'values.user.uuid',
                        ]);
                    if ($user_list_of_workspace->ok()) {
                        $user_list_of_workspace_body = json_decode($user_list_of_workspace->body(), true)['values'];
                        foreach ($user_list_of_workspace_body as $user_data) {
                            $user = Http::withToken($this->provider->accessToken)
                                ->get('https://api.bitbucket.org/2.0/users/' . $user_data['user']['uuid'], [
                                    'fields' => 'account_status,uuid',
                                ]);
                            if($user->ok()){
                                $user = json_decode($user->body(), true);
                                if ($user['account_status'] === 'inactive') {
                                    $active_user = [];
                                    break;
                                }
                                if ($user['account_status'] === 'active') {
                                    array_push($active_user, $user['uuid']);
                                }
                            }
                        }
                    }
                }
                return count($active_user) === 0 ? null : json_encode([
                    'active_users' => count(array_unique($active_user)),
                    'inactive_users' => 0,
                ]);
            }
        } catch (\Exception $e) {
            writeLog('error', 'Bitbucket getInactiveUsersRemoval implementation failed: '.$e->getMessage());
            return null;
        }
        return null;
    }

    // standard:ISO 27001-2-2013,
    // control: A.12.1.2, A.14.2.2
    // requirements:Check that Pull Requests exists,
    // logic used: atleast one pull request exists,
    public function getPullRequestsExists(): ?string
    {
        try {
            foreach ($this->all_Repository as $repository) {
                $all_pullRequest = Http::withToken($this->provider->accessToken)
                    ->get($this->repositoryApi .
                        $repository['work'] . '/' .
                        $repository['repo']['slug'] .
                        '/pullrequests', [
                            'fields' => 'values.type,values.id,values.created_on,values.updated_on,values.state,values.author.uuid',
                            'state' => 'merge,superseded,open,declined',
                        ]);
                if ($all_pullRequest->ok() && !empty($all_pullRequest['values'])) {
                    $all_pullRequest_body = json_decode($all_pullRequest, true)['values'];
                    foreach ($all_pullRequest_body as $single_pullRequest) {
                        $single_pullRequest['author_uuid']= $single_pullRequest['author']['uuid'];

                        $apiResponse = $single_pullRequest;
                        $required_values = ["type" => 'pullrequest'];
                        $additional_values = ['id','state','created_on','updated_on','author_uuid'];
                        $filter_operator = '=';
                        return json_encode($this->formatResponse(
                            $apiResponse,
                            $required_values,
                            $additional_values,
                            $filter_operator
                        ));
                    }
                    unset($single_pullRequest);
                }
            }
            unset($repository);
        } catch (\Exception $e) {
            writeLog('error', 'Bitbucket getPullRequestsExists implementation failed: '.$e->getMessage());
            return null;
        }
        return null;
    }

    // standard:ISO 27001-2-2013,
    // control: A.12.1.2, A.14.2.2
    // requirements:A policy that we do Pull Requests checks are required for master/main,
    // logic used: pull request enabled in branch restriction(main or master),
    public function getPullRequestsRequired(): ?string
    {
        try {
            $branch_names = ['master', 'main'];
            foreach ($this->all_Repository as $Repository) {
                $all_restriction = Http::withToken($this->provider->accessToken)
                    ->get($this->repositoryApi .
                        $Repository['work'] . '/' . $Repository['repo']['slug'] . '/branch-restrictions', [
                            'q' => 'values.kind = "restrict_merges"',
                            'fields' => 'values.kind,values.pattern,values.id,values.type,values.users.uuid',
                        ]);
                if($all_restriction->ok() && in_array(strtolower($all_restriction['values']['0']['pattern']),$branch_names)){
                    $restriction= collect($all_restriction['values']);
                    $mergeAccess= $restriction->where('kind','restrict_merges')->first();
                    if(count($mergeAccess['users']) > 0){
                        //has mergeAcess
                        $mergeAccess['users_with_access']=count($mergeAccess['users']);

                        $apiResponse = $mergeAccess;
                        $required_values = ["kind" => 'restrict_merges'];
                        $additional_values = ['type','pattern','id','users_with_access'];
                        $filter_operator = '=';
                        return json_encode($this->formatResponse(
                            $apiResponse,
                            $required_values,
                            $additional_values,
                            $filter_operator
                        ));
                    }
                }
            }
            unset($Repository);
        } catch (\Exception $e) {
            writeLog('error', 'Bitbucket getPullRequestsRequired implementation failed: '.$e->getMessage());
            return null;
        }
        return null;
    }

    // standard:ISO 27001-2-2013,
    // control: A.12.1.2, A.14.2.2,
    // requirements:Check that adding code to main/master can only be done by Pull Request,
    // logic used: if there is no write access and only merge access for main or master branch,
    public function getProductionBranchRestrictions(): ?string
    {
        try {
            $branch_names = ['master', 'main'];
            foreach ($this->all_Repository as $Repository) {
                $all_restriction = Http::withToken($this->provider->accessToken)
                    ->get($this->repositoryApi .
                        $Repository['work'] . '/' . $Repository['repo']['slug'] . '/branch-restrictions', [
                            'fields' => 'values.kind,values.pattern,values.id,values.type,values.users.uuid',
                        ]);
                if ($all_restriction->ok() && in_array($all_restriction['values'][0]['pattern'], $branch_names)) {
                    $main_branch = collect($all_restriction['values']);
                    $writeAccess = $main_branch->where('kind', 'push')->first();
                    $mergeAccess = $main_branch->where('kind', 'restrict_merges')->first();
                    if (count($writeAccess['users']) === 0 && count($mergeAccess['users']) > 0) {
                        //has merge access
                        $mergeAccess['users_with_access']=count($mergeAccess['users']);
                        $writeAccess['users_with_access']=count($writeAccess['users']);
                        $data=[$mergeAccess,$writeAccess];

                        $apiResponse = $data;
                        $required_values = ["type" => 'branchrestriction'];
                        $additional_values = ['pattern','kind','id','users_with_access'];
                        $filter_operator = '=';
                        return json_encode($this->formatResponse(
                            $apiResponse,
                            $required_values,
                            $additional_values,
                            $filter_operator
                        ));
                    }
                }
            }
            unset($Repository);
        } catch (\Exception $e) {
            writeLog('error', 'Bitbucket getProductionBranchRestrictions implementation failed: '.$e->getMessage());
            return null;
        }
        return null;
    }

    public static function getHowToImplementAction($action): ?string
    {
        $howToImplementActionsArr = [
            "getUniqueAccounts" => "",
            "getGitStatus" => "https://support.atlassian.com/bitbucket-cloud/docs/add-edit-and-commit-to-source-files/",
            "getMfaStatus" => "https://support.atlassian.com/bitbucket-cloud/docs/enable-two-step-verification/",
            "getAdminMfaStatus" => "https://support.atlassian.com/bitbucket-cloud/docs/enable-two-step-verification/",
            "getPrivateRepository" => "https://support.atlassian.com/bitbucket-cloud/docs/set-repository-privacy-and-forking-options/",
            'getInactiveUsersRemoval'=>'https://community.atlassian.com/t5/Bitbucket-questions/How-to-remove-users-from-Bitbucket-Cloud-but-keep-all-repos-and/qaq-p/1367007#:~:text=i%20would%20like,all%20the%20best',
            'getPullRequestsExists'=>  'https://support.atlassian.com/bitbucket-cloud/docs/create-a-pull-request-to-merge-your-change/',
            "getPullRequestsRequired" => "https://bitbucket.org/blog/take-control-with-branch-restrictions",
            "getProductionBranchRestrictions" => "https://bitbucket.org/blog/take-control-with-branch-restrictions",
        ];

        return array_key_exists($action, $howToImplementActionsArr) ? $howToImplementActionsArr[$action] : null;
    }
}
