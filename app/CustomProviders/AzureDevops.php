<?php

namespace App\CustomProviders;

use App\CustomProviders\Interfaces\IDevelopmentTools;
use App\CustomProviders\Interfaces\IHaveHowToImplement;

class AzureDevops extends CustomProvider implements IDevelopmentTools, IHaveHowToImplement
{

    public function __construct()
    {
        parent::__construct('azure-devops', 'https://login.microsoftonline.com/common/oauth2/v2.0/token');
    }

    public function getMfaStatus(): ?string
    {
        return null;
    }

    public function getAdminMfaStatus(): ?string
    {
        return null;
    }

    public static function getHowToImplementAction($action): ?string
    {
        $howToImplementActionsArr = [];

        return array_key_exists($action, $howToImplementActionsArr) ? $howToImplementActionsArr[$action] : null;
    }

    public function getUniqueAccounts(): ?string
    {
        // TODO: Implement getUniqueAccounts() method.
    }

    public function getPrivateRepository(): ?string
    {
        // TODO: Implement getPrivateRepository() method.
    }

    public function getPullRequestsRequired(): ?string
    {
        // TODO: Implement getPullRequestsRequired() method.
    }

    public function getPullRequestsExists(): ?string
    {
        // TODO: Implement getPullRequestsExists() method.
    }

    public function getProductionBranchRestrictions(): ?string
    {
        // TODO: Implement getProductionBranchRestrictions() method.
    }

    public function getInactiveUsersRemoval(): ?string
    {
        // TODO: Implement getInactiveUsersRemoval() method.
    }

    public function getGitStatus(): ?string
    {
        // TODO: Implement getGitStatus() method.
    }
}
