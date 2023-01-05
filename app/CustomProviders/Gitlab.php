<?php

namespace App\CustomProviders;

use App\CustomProviders\Interfaces\IDevelopmentTools;
use App\CustomProviders\Interfaces\IHaveHowToImplement;
use Illuminate\Support\Facades\Http;
use App\Traits\Integration\IntegrationApiTrait;

class Gitlab extends CustomProvider implements IDevelopmentTools, IHaveHowToImplement
{
    use IntegrationApiTrait;

    public function __construct()
    {
        parent::__construct('gitlab', 'https://gitlab.com/oauth/token');
    }

    public static function getHowToImplementAction($action): ?string
    {
        $howToImplementActionsArr = [
            "getMfaStatus" => "https://docs.gitlab.com/ee/user/profile/account/two_factor_authentication.html#enable-two-factor-authentication",
            "getAdminMfaStatus" => "https://docs.gitlab.com/ee/user/profile/account/two_factor_authentication.html#enable-two-factor-authentication",
            "getGitStatus" => "https://docs.gitlab.com/ee/api/commits.html#commits-api",
            "getUniqueAccounts" => "https://docs.gitlab.com/ee/api/users.html#users-api",
            "getInactiveUsersRemoval" => "https://docs.gitlab.com/ee/api/projects.html#list-user-projects",
            "getPrivateRepository" => "https://docs.gitlab.com/ee/api/projects.html#projects-api",
            "getPullRequestsRequired" => "https://docs.gitlab.com/ee/api/projects.html#projects-api",
            "getPullRequestsExists" => "https://docs.gitlab.com/ee/api/merge_requests.html#merge-requests-api",
            "getProductionBranchRestrictions" => "https://docs.gitlab.com/ee/api/projects.html#projects-api",
        ];

        return array_key_exists($action, $howToImplementActionsArr) ? $howToImplementActionsArr[$action] : null;
    }

    public function getMfaStatus(): ?string
    {
        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get('https://gitlab.com/api/v4/user');
            if ($response->ok()) {
                $body = json_decode($response->body(), true);
                $apiResponse = $body['value'] ?? $body;
                $required_values = ["two_factor_enabled" => true];
                $additional_values = ['id','name'];
                $filter_operator = '=';
                return json_encode($this->formatResponse(
                    $apiResponse,
                    $required_values,
                    $additional_values,
                    $filter_operator
                ));
            }

        } catch (\Exception $e) {
            writeLog('error', 'Gitlab getMfaStatus implementation failed: '.$e->getMessage());
        }

        return null;
    }


    public function getAdminMfaStatus(): ?string
    {
        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get('https://gitlab.com/api/v4/user');

            if ($response->ok()) {
                $body = json_decode($response->body(), true);

                $apiResponse = $body['value'] ?? $body;
                $required_values = ["two_factor_enabled"=>true,"can_create_project"=>true,"can_create_group"=>true];
                $additional_values = ['id','name','email','web_url'];
                $filter_operator = '=';

                $formatedResponse = $this->formatResponse($apiResponse,$required_values,$additional_values,$filter_operator);
                return json_encode($formatedResponse);
            }

        } catch (\Exception $e) {
            writeLog('error', 'Gitlab getAdminMfaStatus implementation failed: '.$e->getMessage());
        }

        return null;
    }

    public function getGitStatus(): ?string
    {
        try {
            $getUserResponse = Http::withToken($this->provider->accessToken)
                ->get('https://gitlab.com/api/v4/user');

            if ($getUserResponse->ok()) {
                $getUserResponseBody = json_decode($getUserResponse->body(), true);

                if (count($getUserResponseBody)) {
                    $username = $getUserResponseBody['username'];
                    $getUserReposUrl = 'https://gitlab.com/api/v4/users/' . $username . '/projects';
                    $getUserRepos = Http::withToken($this->provider->accessToken)
                        ->get($getUserReposUrl);

                    $getUserReposResponse = json_decode($getUserRepos->body(), true);

                    foreach ($getUserReposResponse as $item) {
                        $repoId = $item['id'];
                        $commitsUrl = 'https://gitlab.com/api/v4/projects/' . $repoId . '/repository/commits';
                        $commitsUrlResponse = Http::withToken($this->provider->accessToken)
                            ->get($commitsUrl);
                        $commitsArray = json_decode($commitsUrlResponse->body(), true);
                        if (count($commitsArray)) {
                            foreach ($commitsArray as $commit) {
                                if (is_array($commit) && array_key_exists('message', $commit)) {
                                    $data['dev_tool'] = 'Gitlab';
                                    $data['repo_name'] = $item['name'];
                                    $data['message'] = $commit['message'];
                                    $data['committer_name'] = $commit['committer_name'];
                                    $data['committed_date'] = date('M d, Y \a\t g:h A', strtotime($commit['committed_date']));
                                    if (count($data)) {
                                        return json_encode($data);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            writeLog('error', 'Gitlab getGitStatus implementation failed: '.$e->getMessage());
        }

        return null;
    }

    // unique_accounts
    // check all accounts from the service are unique. It should always be true.
    public function getUniqueAccounts():?string
    {
        try{
            $response = Http::withToken($this->provider->accessToken)
                ->get('https://gitlab.com/api/v4/user');
            if ($response->ok()) {
                $body = json_decode($response->body(), true);
                return json_encode([
                    'unique_accounts' => true,
                    'state' => $body["state"]?$body["state"]:"",
                    'unique_username' => $body["username"]?$body["username"]:"",
                ]);
            }
        }catch(\Exception $e){
            writeLog('error', 'Gitlab getUniqueAccounts implementation failed: '.$e->getMessage());
        }
        return null;
    }

    // private_repository
    // Only authorized employees access version control
    public function getPrivateRepository(): ?string
    {
        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get('https://gitlab.com/api/v4/projects',["visibility"=>"private"]);
            if ($response->ok()) {
                $body = json_decode($response->body(), true);
                $apiResponse = $body;
                $required_values = ["visibility"=>"private"];
                $additional_values = ['id','repository_access_level','name_with_namespace','default_branch','visibility','pages_access_level'];
                $filter_operator = '=';

                return json_encode($this->formatResponse(
                    $apiResponse,
                    $required_values,
                    $additional_values,
                    $filter_operator
                ));
            }

        } catch (\Exception $e) {
            writeLog('error', 'Gitlab getPrivateRepository implementation failed: '.$e->getMessage());
        }

        return null;
    }

    // pull_requests_required
    // Only authorized employees change code
    public function getPullRequestsRequired(): ?string
    {
        //Note: Here in gitlab merge request is similar with pull request
        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get('https://gitlab.com/api/v4/projects',["visibility"=>"private"]);
            if ($response->ok()) {
                $body = json_decode($response->body(), true);
                $apiResponse = $body;
                $required_values = ["merge_requests_access_level" => "enabled","merge_requests_enabled"=>true];
                $additional_values = ['id','merge_requests_access_level','merge_requests_enabled'];
                $filter_operator = '=';

                return json_encode($this->formatResponse(
                    $apiResponse,
                    $required_values,
                    $additional_values,
                    $filter_operator
                ));
            }

        } catch (\Exception $e) {
            writeLog('error', 'Gitlab getPullRequestsRequired implementation failed: '.$e->getMessage());
        }

        return null;
    }

    // pull_requests_exists
    // Check that Pull Requests exists
    public function getPullRequestsExists(): ?string
    {
        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get('https://gitlab.com/api/v4/merge_requests?state=opened');
            if ($response->ok()) {
                $body = json_decode($response->body(), true);
                $apiResponse = array_filter($body, function ($each) {
                    return ($each['state'] == 'opened');
                });
                $finalData = [];
                foreach($apiResponse  as $eachResponse){
                    $finalData[] = ["state"=>$eachResponse["state"],"id"=>$eachResponse["id"],"project_id"=>$eachResponse["project_id"],"title"=>$eachResponse["title"],"created_at"=>$eachResponse["created_at"],"target_branch"=>$eachResponse["target_branch"],"merge_status"=>$eachResponse["merge_status"]];
                }
                $required_values = ["state"=>"opened"];
                $additional_values = ['id','project_id','title','created_at','target_branch','merge_status'];
                $filter_operator = '=';

                $formatedResponse = $this->formatResponse(
                    $finalData,
                    $required_values,
                    $additional_values,
                    $filter_operator
                );
                return json_encode($formatedResponse);
            }
        } catch (\Exception $e) {
            writeLog('error', 'Gitlab getPullRequestsExists implementation failed: '.$e->getMessage());
        }
        return null;
    }

    // production_branch_restrictions
    // Check that adding code to main/master can only be done by Pull Request
    public function getProductionBranchRestrictions(): ?string
    {
        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get('https://gitlab.com/api/v4/projects',["visibility"=>"private"]);
            if ($response->ok()) {
                $projects = json_decode($response->body(), true);
                $apiResponse = $projects[0];

                $required_values = ["merge_requests_access_level"=>"enabled"];
                $additional_values = ['id','merge_requests_enabled','merge_requests_access_level','default_branch'];
                $filter_operator = '=';

                return json_encode($this->formatResponse(
                    $apiResponse,
                    $required_values,
                    $additional_values,
                    $filter_operator
                ));
            }

        } catch (\Exception $e) {
            writeLog('error', 'Gitlab getProductionBranchRestrictions implementation failed: '.$e->getMessage());
        }
        return null;
    }


    // inactive_users_removal
    // Check that inactive users are removed.
    public function getInactiveUsersRemoval(): ?string
    {
        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get('https://gitlab.com/api/v4/projects',["visibility"=>"private"]);
            if ($response->ok()) {
                $projects = json_decode($response->body(), true);
                $getFirstProjectId = $projects[0]?$projects[0]["id"]:"";
                if(!empty($getFirstProjectId)){
                    //Get all user from the project
                    $responseUser = Http::withToken($this->provider->accessToken)
                        ->get('https://gitlab.com/api/v4/projects/'.$getFirstProjectId.'/users');
                    if ($responseUser->ok()) {
                        $users = json_decode($responseUser->body(), true);
                        $activeuser = $inactiveUser = 0;
                        foreach ($users as $eachUser) {
                            if($eachUser['state'] == "active"){
                                $activeuser++;
                            }else{
                                $inactiveUser++;
                            }
                        }
                        return json_encode([
                            'active_users' => $activeuser,
                            'inactive_users' => $inactiveUser,
                        ]);
                    }
                }

            }
        } catch (\Exception $e) {
            writeLog('error', 'Gitlab getInactiveUsersRemoval implementation failed: '.$e->getMessage());
        }
        return null;
    }
}
