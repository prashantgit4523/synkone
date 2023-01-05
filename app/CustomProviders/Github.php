<?php

namespace App\CustomProviders;

use Illuminate\Support\Facades\Http;
use App\Traits\Integration\IntegrationApiTrait;
use App\CustomProviders\Interfaces\IDevelopmentTools;
use App\CustomProviders\Interfaces\IHaveHowToImplement;

class Github extends CustomProvider implements IDevelopmentTools, IHaveHowToImplement
{
    use IntegrationApiTrait;
    private string $gitRepoUrl = 'https://api.github.com/repos/';

    public function __construct()
    {
        parent::__construct('github', 'https://github.com/login/oauth/access_token');
    }
    /**
     * It returns a JSON string containing the user's GitHub ID, username, and whether or not they have
     * two-factor authentication enabled
     * @return string|null 
     */

    public function getMfaStatus(): ?string
    {
        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get('https://api.github.com/user');

            if ($response->ok()) {
                $body = json_decode($response->body(), true);

                $apiResponse = $body['value'] ?? $body;

                $required_values = ["two_factor_authentication" => true];
                $additional_values = ['id', 'login'];
                $filter_operator = '=';

                return json_encode($this->formatResponse(
                    $apiResponse,
                    $required_values,
                    $additional_values,
                    $filter_operator
                ));
            }
        } catch (\Exception $e) {
            writeLog('error', 'Github getMfaStatus implementation failed: '.$e->getMessage());
        }

        return null;
    }

    /**
     * This function returns a JSON string containing the user's GitHub account information,
     * including whether or not the user has two-factor authentication enabled and user is a site admin
     * @return string|null 
     */
    public function getAdminMfaStatus(): ?string
    {
        try {

            $response = Http::withToken($this->provider->accessToken)
                ->get('https://api.github.com/user');

            if ($response->ok()) {
                $body = json_decode($response->body(), true);

                $apiResponse = $body['value'] ?? $body;

                $required_values = ["two_factor_authentication" => true, "site_admin" => true];
                $additional_values = ['id', 'login'];
                $filter_operator = '=';

                return json_encode($this->formatResponse(
                    $apiResponse,
                    $required_values,
                    $additional_values,
                    $filter_operator
                ));
            }
        } catch (\Exception $e) {
            writeLog('error', 'Github getAdminMfaStatus implementation failed: '.$e->getMessage());
        }

        return null;
    }

    public static function getHowToImplementAction($action): ?string
    {
        $howToImplementActionsArr = [
            "getMfaStatus" => "https://docs.github.com/en/authentication/securing-your-account-with-two-factor-authentication-2fa/configuring-two-factor-authentication",
            "getAdminMfaStatus" => "https://docs.github.com/en/authentication/securing-your-account-with-two-factor-authentication-2fa/configuring-two-factor-authentication",
            "getUniqueAccounts" => "https://docs.github.com/en/rest/users/users#get-a-user",
            "getInactiveUsersRemoval" => "",
            "getPrivateRepository" => "https://docs.github.com/en/rest/repos/repos#get-a-repository",
            "getPullRequestsRequired" => "https://docs.github.com/en/pull-requests/collaborating-with-pull-requests/proposing-changes-to-your-work-with-pull-requests/creating-a-pull-request",
            "getPullRequestsExists" => "https://docs.github.com/en/pull-requests/collaborating-with-pull-requests/proposing-changes-to-your-work-with-pull-requests/creating-a-pull-request",
            "getProductionBranchRestrictions" => "https://docs.github.com/en/repositories/configuring-branches-and-merges-in-your-repository/defining-the-mergeability-of-pull-requests/managing-a-branch-protection-rule",
            "getGitStatus" => "https://docs.github.com/en/get-started/quickstart/create-a-repo#commit-your-first-change",
        ];

        return array_key_exists($action, $howToImplementActionsArr) ? $howToImplementActionsArr[$action] : null;
    }

    /**
     * It gets the latest commit from the user's Github account and returns it as a JSON string
     * Standard: ISO 27001-2-2013
     * Control: A.14.2.2
     * @return string|null
     */
    public function getGitStatus(): ?string
    {
        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get('https://api.github.com/user/repos');

            if ($response->ok()) {
                $body = json_decode($response->body(), true);

                if (count($body)) {
                    foreach ($body as $item) {
                        $repoName = $item['name'];
                        $owner = $item['owner']['login'];
                        $getCommitsResponse = Http::withToken($this->provider->accessToken)
                            ->get($this->gitRepoUrl . $owner . '/' . $repoName . '/commits');
                        $commitsArray = json_decode($getCommitsResponse->body(), true);
                        if (count($commitsArray)) {
                            foreach ($commitsArray as $commit) {
                                if (is_array($commit) && array_key_exists('sha', $commit)) {
                                    $data['dev_tool'] = 'Github';
                                    $data['repo_name'] = $repoName;
                                    $data['message'] = $commit['commit']['message'];
                                    $data['committer_name'] = $commit['commit']['committer']['name'];
                                    $data['committed_date'] = date('M d, Y \a\t g:h A', strtotime($commit['commit']['committer']['date']));
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
            writeLog('error', 'Github getGitStatus implementation failed: '.$e->getMessage());
        }

        return null;
    }


    /** 
     * Its returned connected user should be unique.
     * Standard: ISO 27001-2-2013
     * Control : A.9.2.2, A.9.2.5
     *  @return ?string The name and login of the user.
     */
    public function getUniqueAccounts(): ?string
    {
        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get('https://api.github.com/user');
                if ($response->ok()) {
                    $body = json_decode($response->body(), true);
                    return json_encode([
                        'unique_accounts' => true,
                        'name' => $body['name'],
                        'login' => $body['login'],
                    ]);
                }
       }catch(\Exception $e){
            writeLog('error', 'Github getUniqueAccount implementation failed: '.$e->getMessage());
       }
        return null;
    }

    /**
     * It returns a list of private repositories for the authenticated user
     * Standard: ISO 27001-2-2013
     * Control: A.9.2.1
     * @return string|null
     */
    public function getPrivateRepository(): ?string
    {
        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get('https://api.github.com/user/repos', ["visibility" => "private"]);
            if ($response->ok()) {
                $body = json_decode($response->body(), true);
                $apiResponse = $body;
                $required_values = ["visibility" => "private"];
                $additional_values = ['id', 'name', 'full_name', 'default_branch', 'visibility'];
                $filter_operator = '=';
                return json_encode($this->formatResponse(
                    $apiResponse,
                    $required_values,
                    $additional_values,
                    $filter_operator
                ));
            }
        }catch(\Exception $e){
            writeLog('error', 'Github getPrivateRepository implementation failed: '.$e->getMessage());
        }
        return null;
    }

    /**
     * It gets all the pull requests for all the repositories of the user
     * Standard: ISO 27001-2-2013
     * Control: A.12.1.2, A.14.2.2
     * @return string|null
     */
    public function getPullRequestsRequired(): ?string
    {
        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get('https://api.github.com/user/repos');
            if ($response->ok()) {
                $body = json_decode($response->body(), true);
                if (count($body)) {
                    foreach ($body as $item) {
                        $owner = $item['owner']['login'];
                        $repoName = $item['name'];
                        $pullsUrl = $this->gitRepoUrl . $owner . '/' . $repoName . '/pulls';
                        $getPullsResponse = Http::withToken($this->provider->accessToken)
                            ->get($pullsUrl);
                        $pullsArray = json_decode($getPullsResponse->body(), true);
                        if (count($pullsArray)) {
                            $required_values = ["type" => "User",];
                            $additional_values = ['id', 'number', 'title', 'body', 'state'];
                            $filter_operator = '=';

                            return json_encode($this->formatResponse(
                                $pullsArray,
                                $required_values,
                                $additional_values,
                                $filter_operator
                            ));
                        }
                    }
                }
            }
        }catch(\Exception $e){
            writeLog('error', 'Github getPullRequestsRequired implementation failed: '.$e->getMessage());
        }
        return null;
    }

    /**
     * It check pull request & returns
     * Standard: ISO 27001-2-2013
     * Control: A.12.1.2, A.14.2.2
     * @return string|null
     */
    public function getPullRequestsExists(): ?string
    {
        try {
            $data = [];
            $response = Http::withToken($this->provider->accessToken)
                ->get('https://api.github.com/user/repos');
            if ($response->ok()) {
                $body = json_decode($response->body(), true);
                if (count($body)) {
                    foreach ($body as $item) {
                        $owner = $item['owner']['login'];
                        $repoName = $item['name'];
                        $pullsUrl = $this->gitRepoUrl . $owner . '/' . $repoName . '/pulls';
                        $getPullsResponse = Http::withToken($this->provider->accessToken)
                            ->get($pullsUrl);
                        $pullsArray = json_decode($getPullsResponse->body(), true);
                        if (count($pullsArray)) {
                            foreach ($pullsArray as $pull) {
                                if (count($pull) > 2) {
                                    $data['id'] = $pull['id'];
                                    $data['pull_request_name'] = $pull['user']['login'];
                                    $data['pull_request_title'] = $pull['title'];
                                    $data['pull_request_number'] = $pull['number'];
                                    $data['type'] = $pull['user']['type'];
                                    $data['created_at'] = date('M d, Y \a\t g:h A', strtotime($pull['created_at']));
                                    $data['updated_at'] = date('M d, Y \a\t g:h A', strtotime($pull['updated_at']));
                                    break;
                                }
                            }
                        }
                    }
                    if (count($data)) {
                        return json_encode($data);
                    }
                }
            }
        }catch(\Exception $e){
            writeLog('error', 'Github getPullRequestsExists implementation failed: '.$e->getMessage());
        }
        return null;
    }

    /**
     * It checks if the default branch of a repository is protected. If it is, it returns the name of
     * the repository and the name of the protected branch
     * Standard: ISO 27001-2-2013
     * Control: A.12.1.2, A.14.2.2
     * @return string|null
     */
    public function getProductionBranchRestrictions(): ?string
    {
        try {
            $data = [];
            $response = Http::withToken($this->provider->accessToken)
                ->get('https://api.github.com/user/repos');
            if ($response->ok()) {
                $body = json_decode($response->body(), true);
                if (count($body)) {
                    foreach ($body as $item) {
                        $branchUrl = $this->gitRepoUrl . $item['owner']['login'] . '/' . $item['name'] . '/branches' . '/' . $item['default_branch'] . '/protection';
                        $getBranchResponse = Http::withToken($this->provider->accessToken)
                            ->get($branchUrl);
                        $branchArray = json_decode($getBranchResponse->body(), true);
                        if (count($branchArray) && array_key_exists('required_pull_request_reviews', $branchArray)) {

                            $data['protected_repository'] = $item['name'];
                            $data['protected_branch'] = $item['default_branch'];
                            $data['production_branch_restrictions'] = true;
                            break;
                        }
                    }
                    if (count($data)) {
                        return json_encode($data);
                    }
                }
            }
       }catch(\Exception $e){
         writeLog('error', 'Github getProductionBranchRestrictions implementation failed: '.$e->getMessage());
       }
        return null;
    }


    /**
     * It looks for inactive users in the org|repo.
     * Standard: ISO 27001-2-2013
     * Control: A.9.2.6
     * @return ?string|null a JSON string of the users who have not been active for more than 3 months.
     */
    public function getInactiveUsersRemoval(): ?string
    {
        try {
            $response = Http::withToken($this->provider->accessToken)
                ->get('https://api.github.com/user/repos');
            if ($response->ok()) {
                $body = json_decode($response->body(), true);
                if (count($body)) {
                    foreach ($body as $item) {
                        $repoEventUrl = Http::withToken($this->provider->accessToken)
                            ->get($this->gitRepoUrl . $item['owner']['login'] . '/' . $item['name'] . '/events');
                        $repoEventArray = json_decode($repoEventUrl->body(), true);
                        if (count($repoEventArray)) {
                            foreach ($repoEventArray as $event) {
                                /**
                                 * Check if the created event is more than 3 months ago then 
                                 * we can assume that the user was active at the time of creation.
                                 */
                                if (array_key_exists('created_at', $event) && $event['created_at'] !== null) {
                                    $created_at = date_create($event['created_at']);
                                    $now = date_create();
                                    $interval = date_diff($created_at, $now);
                                    $months = $interval->format('%m');
                                    if ($months > 3) {
                                        $apiResponse = $event['actor'];
                                        $required_values = [];
                                        $additional_values = ['id', 'display_login', 'login'];
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
                        }
                    }
                }
            }
        }catch(\Exception $e){
            writeLog('error', 'Github getInactiveUsersRemoval implementation failed: '.$e->getMessage());
        }
        return null;
    }
}
