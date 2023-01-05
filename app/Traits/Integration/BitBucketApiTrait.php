<?php

namespace App\Traits\Integration;

use Illuminate\Support\Facades\Http;

trait BitBucketApiTrait
{
    private function getRepositories()
    {
        $data = [];
        $workspace = Http::withToken($this->provider->accessToken)
            ->get('https://api.bitbucket.org/2.0/user/permissions/workspaces', [
                'fields' => 'values.workspace.uuid',
            ]);
        if ($workspace->ok()) {
            $workspace_body = json_decode($workspace->body(), true)['values'];
            foreach ($workspace_body as $single_workspace) {
                $repo = Http::withToken($this->provider->accessToken)
                    ->get($this->repositoryApi . $single_workspace['workspace']['uuid'], [
                        'fields' => 'values.slug,values.is_private,values.uuid,values.description,values.project.name,values.created_on',
                    ]);
                if ($repo->ok() && !empty($repo['values'])) {
                    $repo_body = json_decode($repo->body(), true)['values'];
                    foreach ($repo_body as $single_repo) {
                        array_push($data,[
                            'work'=> $single_workspace['workspace']['uuid'],
                            'repo'=>$single_repo,
                        ]);
                    }
                }
            }
            return $data;
        }
    }
}
