<?php

namespace App\Models\Integration;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Staudenmeir\EloquentHasManyDeep\HasRelationships;

class IntegrationAction extends Model
{
    use HasFactory;
    use HasRelationships;

    protected $appends = ['action_name'];

    public function integration_controls()
    {
        return $this->belongsToMany(IntegrationControl::class);
    }

    public function integration_provider()
    {
        return $this->belongsTo(IntegrationProvider::class, 'integration_provider_id', 'id');
    }

    public function getActionNameAttribute()
    {
        $action_name = $this->action;
        $action_name_mappings = $this->getActionNameMappings();
        if(array_key_exists($action_name, $action_name_mappings)){
            return $action_name_mappings[$action_name];
        }

        $action_name = substr($action_name, 3);
        $action_name = preg_replace("([A-Z])", " $0", $action_name);
        $action_name = ucfirst(trim(strtolower($action_name)));
        $action_name = explode(' ', $action_name);
        return implode(' ', array_map(function ($word) {
            switch (strtolower($word)) {
                case 'mfa':
                    $result = 'MFA';
                    break;
                case 'ldap':
                    $result = 'LDAP';
                    break;
                case 'waf':
                    $result = 'WAF';
                    break;
                case 'hdd':
                    $result = 'HDD';
                    break;
                default:
                    $result = $word;
            };

            return $result;
        }, $action_name));
    }

    private function getActionNameMappings() {
        return [
            'getMfaStatus' => 'MFA status',
            'getAdminMfaStatus' => 'Admin MFA status',
            'getGitStatus' => 'Git status',
            'getMobileDeviceStatus' => 'Mobile device status',
            'getConditionalAccessStatus' => 'Conditional access status',
            'getBlockedUsbStatus' => 'Blocked USB status',
            'getPasswordComplexityStatus' => 'Password complexity status',
            'getHddEncryptionStatus' => 'HDD encryption status',
            'getInactivityStatus' => 'Inactivity status',
            'getAntivirusStatus' => 'Antivirus status',
            'getNtpStatus' => 'NTP status',
            'getLocalAdminRestrictStatus' => 'Local admin restriction status',
            'getInventoryOfAssets' => 'Inventory of assets status',
            'getOwnershipOfAssets' => 'Ownership of assets status',
            'getChangeManagementFlowStatus' => 'Change management flow status',
            'getIncidentReportStatus' => 'Incident report status',
            'GetLessonsLearnedIncidentReportStatus' => 'Lessons learned incident report status',
            'getClassificationStatus' => 'Classification status',
            'getSecureDataWipingStatus' => 'Secure data wiping status',
            'getInactiveUsersStatus' => 'Inactive users status',
            'getNetworkSegregationStatus' => 'Network segregation status',
            'getKeyvaultStatus' => 'Keyvault status',
            'getCpuMonitorStatus' => 'CPU monitor status',
            'getBackupsStatus' => 'Backups status',
            'getLoggingStatus' => 'Logging status',
            'getWafStatus' => 'WAF status',
            'getEmailEncryptionStatus' => 'Email encryption status',
            'getOAuth2StatusConnection' => 'OAuth2 connection status'
        ];
    }
}
