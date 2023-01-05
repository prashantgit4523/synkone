<?php

namespace App\CustomProviders\Interfaces;
interface IInfrastructure
{
    public function getWafStatus(): ?string;

    public function getMfaStatus(): ?string;

    public function getKeyvaultStatus(): ?string;

    public function getLoggingStatus(): ?string;

    public function getCpuMonitorStatus(): ?string;

    public function getBackupsStatus(): ?string;

    public function getNetworkSegregationStatus(): ?string;

    public function getClassificationStatus(): ?string;

    public function getInactiveUsersStatus(): ?string;

    public function getSecureDataWipingStatus(): ?string;

    public function getAdminMfaStatus(): ?string;

    public function getHddEncryptionStatus(): ?string;

}
