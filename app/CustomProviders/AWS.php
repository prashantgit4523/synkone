<?php

namespace App\CustomProviders;

use App\CustomProviders\Interfaces\ICustomAuth;
use App\CustomProviders\Interfaces\IHaveHowToImplement;
use App\CustomProviders\Interfaces\IInfrastructure;
use Aws\Backup\BackupClient;
use Aws\CloudWatch\CloudWatchClient;
use Aws\CloudWatchLogs\CloudWatchLogsClient;
use Aws\Ec2\Ec2Client;
use Aws\Iam\IamClient;
use Aws\Kms\KmsClient;
use Aws\Waf\WafClient;
use Aws\WafRegional\WafRegionalClient;
use Aws\WAFV2\WAFV2Client;
use Illuminate\Support\Arr;

class AWS extends CustomProvider implements ICustomAuth, IInfrastructure, IHaveHowToImplement
{
    public function __construct()
    {
        parent::__construct('aws');
    }

    public function attempt(array $fields): bool
    {
        $client = new IamClient([
            'version' => '2010-05-08',
            'region' => $fields['region'],
            'credentials' => [
                'key' => $fields['access_key_id'],
                'secret' => $fields['secret_access_key']
            ]
        ]);

        try {
            $client->getUser();

            // check region
            $ec2_client = new Ec2Client([
                'version' => '2016-11-15',
                'region' => $fields['region'],
                'credentials' => [
                    'key' => $fields['access_key_id'],
                    'secret' => $fields['secret_access_key']
                ]
            ]);
            $ec2_client->describeRegions();

            $this->connect($this->provider, $fields);
            return true;
        } catch (\Exception $e) {
            writeLog('error', 'Aws attempt to connect failed: '.$e->getMessage());
        }

        return false;
    }

    public function getWafStatus(): ?string
    {
        $waf_regional_client = new WafRegionalClient($this->getCredentials('2016-11-28'));
        $waf_v2_client = new WAFV2Client($this->getCredentials('2019-07-29'));
        $waf_client = new WafClient($this->getCredentials('2015-08-24'));

        $all_acls = [];

        try {
            $acls = $waf_regional_client->listWebACLs();
            $all_acls = [...$all_acls, ...$acls['WebACLs']];
        } catch (\Exception $e) {
        }

        try {
            $acls = $waf_v2_client->listWebACLs(['Scope' => 'CLOUDFRONT']);
            $all_acls = [...$all_acls, ...$acls['WebACLs']];
        } catch (\Exception $e) {
        }

        try {
            $acls = $waf_v2_client->listWebACLs(['Scope' => 'REGIONAL']);
            $all_acls = [...$all_acls, ...$acls['WebACLs']];
        } catch (\Exception $e) {
        }

        try {
            $acls = $waf_client->listWebACLs();
            $all_acls = [...$all_acls, ...$acls['WebACLs']];
        } catch (\Exception $e) {
        }

        if (!empty($all_acls)) {
            return json_encode($all_acls);
        }

        return null;
    }

    public function getMfaStatus(): ?string
    {
        $client = new IamClient($this->getCredentials('2010-05-08'));

        try {
            $mfa_devices = $client->listMFADevices();
            $mfa_devices = $mfa_devices['MFADevices'];

            if (!empty($mfa_devices)) {
                return json_encode($mfa_devices);
            }
        } catch (\Exception $e) {
            writeLog('error', 'Aws getMfaStatus implementation failed: '.$e->getMessage());
        }

        return null;
    }

    public function getKeyvaultStatus(): ?string
    {
        $client = new KmsClient($this->getCredentials('2014-11-01'));

        try {
            $keys = $client->listKeys();
            $keys = $keys['Keys'];

            if (!empty($keys)) {
                return json_encode($keys);
            }
        } catch (\Exception $e) {
            writeLog('error', 'Aws getKeyvaultStatus implementation failed: '.$e->getMessage());
        }

        return null;
    }

    public function getLoggingStatus(): ?string
    {
        $client = new CloudWatchLogsClient($this->getCredentials('2014-03-28'));

        try {
            $logs = $client->describeLogGroups();
            $logs = $logs['logGroups'];

            if (!empty($logs)) {
                return json_encode($logs);
            }
        } catch (\Exception $e) {
            writeLog('error', 'Aws getLoggingStatus implementation failed: '.$e->getMessage());
        }

        return null;
    }

    public function getCpuMonitorStatus(): ?string
    {
        $client = new CloudWatchClient($this->getCredentials('2010-08-01'));

        try {
            $alarms = $client->describeAlarms();
            $alarms = $alarms['MetricAlarms'];

            $alarms = Arr::where($alarms, fn ($v) => $v['MetricName'] === 'CPUUtilization');

            if (!empty($alarms)) {
                return json_encode($alarms);
            }
        } catch (\Exception $e) {
            writeLog('error', 'Aws getCpuMonitorStatus implementation failed: '.$e->getMessage());
        }

        return null;
    }

    public function getNetworkSegregationStatus(): ?string
    {
        $client = new Ec2Client($this->getCredentials('2016-11-15'));
        $entries = [];

        try {
            $acls = $client->describeNetworkAcls();
            $acls = $acls['NetworkAcls'];

            foreach ($acls as $acl) {
                if (array_key_exists('Entries', $acl)) {
                    foreach ($acl['Entries'] as $entry) {
                        if (array_key_exists('RuleAction', $entry) && $entry['RuleAction'] === 'deny') {
                            $entries[] = $entry;
                        }
                    }
                }
            }

            if (!empty($entries)) {
                return json_encode($entries);
            }
        } catch (\Exception $e) {
            writeLog('error', 'Aws getNetworkSegregationStatus implementation failed: '.$e->getMessage());
        }

        return null;
    }

    public function getBackupsStatus(): ?string
    {
        $client = new BackupClient($this->getCredentials('2018-11-15'));

        try {
            $backups = $client->listBackupPlans();
            $backups = $backups['BackupPlansList'];

            if (!empty($backups)) {
                return json_encode($backups);
            }
        } catch (\Exception $e) {
            writeLog('error', 'Aws getBackupsStatus implementation failed: '.$e->getMessage());
        }

        return null;
    }

    private function getCredentials(string $version): array
    {
        return [
            'version' => $version ?? 'latest',
            'region' => $this->fields['region'],
            'credentials' => [
                'key' => $this->fields['access_key_id'],
                'secret' => $this->fields['secret_access_key']
            ]
        ];
    }

    public function getHddEncryptionStatus(): ?string
    {
        $client = new Ec2Client($this->getCredentials('2016-11-15'));

        try {
            $result = $client->describeVolumes()->toArray();
            foreach ($result['Volumes'] as $volume) {
                if (array_key_exists('Encrypted', $volume) && $volume['Encrypted']) {
                    return json_encode($volume);
                }
            }
        } catch (\Exception $e) {
            writeLog('error', 'Aws getHddEncryptionStatus implementation failed: '.$e->getMessage());
        }

        return null;
    }

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
        return json_encode(["message" => "AWS is deleting all data securely."]);
    }

    public function getAdminMfaStatus(): ?string
    {
        return null;
    }

    public static function getHowToImplementAction($action): ?string
    {
        $howToImplementActionsArr = [
            "getWafStatus" => "https://docs.aws.amazon.com/waf/latest/developerguide/web-acl.html",
            "getLoggingStatus" => "https://docs.aws.amazon.com/AmazonCloudWatch/latest/logs/WhatIsCloudWatchLogs.html",
            "getHddEncryptionStatus" => "https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/EBSEncryption.html",
            "getBackupsStatus" => "https://aws.amazon.com/backup/?whats-new-cards.sort-by=item.additionalFields.postDateTime&whats-new-cards.sort-order=desc",
            "getNetworkSegregationStatus" => "https://docs.aws.amazon.com/vpc/latest/userguide/vpc-network-acls.html",
            "getCpuMonitorStatus" => "https://docs.aws.amazon.com/AWSEC2/latest/UserGuide/viewing_metrics_with_cloudwatch.html#ec2-cloudwatch-metrics",
            "getKeyvaultStatus" => "https://aws.amazon.com/kms/",
            "getMfaStatus" => "https://aws.amazon.com/iam/features/mfa/"
        ];

        return array_key_exists($action, $howToImplementActionsArr) ? $howToImplementActionsArr[$action] : null;
    }
}
