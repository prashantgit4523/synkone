<?php

namespace App\CustomProviders\Interfaces;
interface IDevelopmentTools
{
    public function getMfaStatus(): ?string;

    public function getAdminMfaStatus(): ?string;

    public function getGitStatus(): ?string;

    public function getUniqueAccounts(): ?string;

    public function getPrivateRepository(): ?string;

    public function getInactiveUsersRemoval(): ?string;

    public function getPullRequestsExists(): ?String;

    public function getProductionBranchRestrictions(): ?string;

    public function getPullRequestsRequired(): ?string;
}
