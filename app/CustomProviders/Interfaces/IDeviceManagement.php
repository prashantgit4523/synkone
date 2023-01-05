<?php

namespace App\CustomProviders\Interfaces;
interface IDeviceManagement
{
    public function getBlockedUsbStatus(): ?string;

    public function getPasswordComplexityStatus(): ?string;

    public function getConditionalAccessStatus(): ?string;

    //MDM, MAM
    public function getMobileDeviceStatus(): ?string;

    public function getHddEncryptionStatus(): ?string;

    public function getInactivityStatus(): ?string;

    public function getAntivirusStatus(): ?string;

    public function getNtpStatus(): ?string;

    public function getLocalAdminRestrictStatus(): ?string;
}
