<?php

namespace App\Traits\Kpi;

trait jumpCloudKpiTrait
{
    //get password complexity details
    private function getPasswordComplexityKpiData(): bool|string|null
    {
        try {
            $allSettings = $this->client->get('/settings');

            if ($allSettings->ok()) {
                $data_to_return = $this->getPasswordSettings($allSettings);

                if (count($data_to_return) > 0) {
                    $user_count = $this->getTotalNoOfUsers();
                    if ($user_count > 0) {
                        return json_encode([
                            'passed' => $user_count,
                            'total' => $user_count,
                        ]);
                    }
                }
            }
            return null;
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with getPasswordComplexityKpiData on JumpCloud KPI Trait');
        }
        return null;
    }

    //checks whether the condition is met
    private function getPasswordSettings($allSettings) : ?array
    {
        $password_complexity = ['needsLowercase', 'needsUppercase', 'needsNumeric', 'needsSymbolic', 'enableMinLength'];
        $passwordPolicy = collect(json_decode($allSettings, true)['SETTINGS']['passwordPolicy'])->only($password_complexity);
        $data_to_return = [];
        foreach ($passwordPolicy as $complexity => $value) {
            if ($value !== false) {
                $data_to_return = array_merge($data_to_return, [$complexity => $value]);
            }
        }

        return $data_to_return;
    }

    //get MFA Status
    private function getConditionalAccessStatusKpiData(): ?string
    {
      try {
         $users = $this->client->get('users?sort=email',[
            'fields'=>'enableMultiFactor'
         ]);
         if ($users->ok()) {
            $users_body = json_decode($users->body(), true);
            $mfaResults = collect($users_body['results']);
            $mfaResults = $mfaResults->pluck('enableMultiFactor');
            $mfaResults = $mfaResults->filter(function ($value) {
                return $value === true;
            });
            $mfaResults = $mfaResults->count();
            if ($mfaResults > 0) {
                return json_encode([
                    'passed' => $mfaResults,
                    'total' => $users_body['totalCount'],
                ],true) ?? null;
            }
         }

        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with getConditionalAccessStatusKpiData on JumpCloud KPI Trait');
        }
        return null;
    }

//get total no fo users 
    private function getTotalNoOfUsers()
    {
        try {
            return $this->client->get('users?sort=email&fields=totalCount')['totalCount'];
        } catch (\Exception $e) {
            $this->logException($e, 'Something went wrong with getTotalNoOfUsers on JumpCloud KPI Trait');
        }
        return null;
    }
}
