<?php

namespace App\CustomProviders\Kpi;

use Illuminate\Support\Facades\Http;
use App\CustomProviders\CustomProvider;
use App\Traits\Integration\IntegrationApiTrait;
use App\CustomProviders\Interfaces\IInfrastructure;
use App\CustomProviders\Interfaces\IHaveHowToImplement;

class Azure extends CustomProvider implements IInfrastructure, IHaveHowToImplement
{
    use IntegrationApiTrait;

    public function __construct()
    {
        parent::__construct('azure', 'https://login.microsoftonline.com/common/oauth2/v2.0/token');
    }

    public function getWafStatus(): ?string
    {
        return null;
    }

    public function getMfaStatus(): ?string
    {
        return null;
    }

    public function getKeyvaultStatus(): ?string
    {
        return null;
    }

    public function getLoggingStatus(): ?string
    {
        return null;
    }

    public function getCpuMonitorStatus(): ?string
    {
        return null;
    }

    public function getBackupsStatus(): ?string
    {
        return null;
    }

    public function getNetworkSegregationStatus(): ?string
    {
        return null;
    }

    // condition: get active policy
    // Logic used: Can't get the requirement so paused by amar.
    // Standard: ISO 27001-2-2013
    // control : A.8.2.1
    public function getClassificationStatus(): ?string
    {
        return null;
    }

    public function getInactiveUsersStatus(): ?string
    {
        return null;
    }

    public function getSecureDataWipingStatus(): ?string
    {
        return null;
    }

    public function getAdminMfaStatus(): ?string
    {
        return null;
    }

    public function getHddEncryptionStatus(): ?string
    {
        return null;
    }

    public static function getHowToImplementAction($action): ?string
    {
        $howToImplementActionsArr = [
            "getClassificationStatus" => "https://docs.microsoft.com/en-us/microsoft-365/compliance/create-sensitivity-labels?view=o365-worldwide",
        ];

        return array_key_exists($action, $howToImplementActionsArr) ? $howToImplementActionsArr[$action] : null;
    }
}
