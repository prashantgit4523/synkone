<?php

namespace App\CustomProviders\Interfaces;

interface IAssetProvider
{
    public function getProjects(): array;
    public function getAssets(): array;
    public function getChangeManagementFlowStatus(): ?string;
    public function getIncidentReportStatus(): ?string;
    public function getLessonsLearnedIncidentReportStatus(): ?string;
    public function getInventoryOfAssets(): ?string;
    public function getOwnershipOfAssets(): ?string;
}