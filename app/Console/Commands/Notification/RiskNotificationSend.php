<?php

namespace App\Console\Commands\Notification;

use App\Mail\RiskManagement\RiskClose;
use App\Models\RiskManagement\RiskNotification;
use App\Models\RiskManagement\RiskRegister;
use App\Models\UserManagement\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class RiskNotificationSend extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'risk_notification:send';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sends risk notification emails';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        Log::info('Risk notification sending process initiated');
        $possibleRisksNotification = RiskNotification::select('risk_id')->get()->pluck('risk_id');
        $allAdmins = Admin::select('id', 'first_name', 'last_name', 'email')->get();
        $this->updateRiskNotificationTableWithMails($possibleRisksNotification, $allAdmins);
        $this->sendClosedEmail($allAdmins);
        return 0;
    }

    //updates db with correct data for risks
    //this is so because this work cannot be done in observer due to linked control cases
    private function updateRiskNotificationTableWithMails($riskIds, $admins)
    {
        try {
            $riskRegister = RiskRegister::whereIn('id', $riskIds)
                ->with('custodian', 'owner', 'mappedControls', 'project')
                ->get();
            //delete risk that have been opened
            $openRisks = $riskRegister->where('status', 'Open')->pluck('id');
            RiskNotification::whereIn('risk_id', $openRisks)->delete();
            $allRiskRegister = $riskRegister->whereNotIn('id', $openRisks)->toArray();
            //enter data in risk notification
            array_map(function ($riskRegister) use ($admins) {
                $notificationDetails = [
                    'risk_name' => $riskRegister['name'],
                    'risk_project_name' => $riskRegister['project']['name'],
                    'treatment_options' => $riskRegister['treatment_options'],
                    'status' => $riskRegister['status'],
                ];
                if ($riskRegister['mapped_controls']) {
                    $notificationDetails = array_merge($notificationDetails, [
                        'project_control_id' => $riskRegister['mapped_controls'][0]['id'],
                        'project_control_name' => $riskRegister['mapped_controls'][0]['name'],
                        'project_name' => $riskRegister['mapped_controls'][0]['project']['name'],
                    ]);
                }
                $responsibleUsers = $this->getResponsibleUsers($riskRegister, $admins);
                $notificationDetails = array_merge($notificationDetails, $responsibleUsers);
                RiskNotification::where('risk_id', $riskRegister['id'])->update($notificationDetails);
            }, $allRiskRegister);
        } catch (\Exception $th) {
            writeLog("error", "could not update risk notification table with emails" . $th->getMessage());
            return $th;
        }
    }

    private function sendClosedEmail($allAdmins)
    {
        try {
            $riskNotificationDb = RiskNotification::all();
            if ($riskNotificationDb) {
                Log::info('Closed risk notification sending process started');
                DB::beginTransaction();

                $riskNotificationOrderByReceiver = $this->riskNotificationOrderByReceiver($riskNotificationDb);
                $this->formatAndSendRiskClosedNotifications($riskNotificationOrderByReceiver, $allAdmins);
                $riskNotificationDb->each->delete();

                DB::commit();
                Log::info('Closed Risk notification sent successfully');
            }
        } catch (\Exception $th) {
            DB::rollback();
            writeLog('error', 'Closed Risk notification sending process failed because' . $th->getMessage());
        }
    }

    //find the responsible, approver of control or custodian,owner and project owner of the risk
    private function getResponsibleUsers($riskRegister, $admins)
    {
        try {
            $implementComplianceControl = $riskRegister['mapped_controls'] ?
                $riskRegister['mapped_controls'][0] : false;

            $responsibleUsers = [];
            if ($riskRegister['owner'] && $riskRegister['custodian']) {
                $responsibleUsers = array_merge(
                    $responsibleUsers,
                    [
                        'receiver1_email' => $riskRegister['owner']['email'],
                    ],
                    [
                        'receiver2_email' => $riskRegister['custodian']['email'],
                    ]
                );
            } elseif ($implementComplianceControl) {
                if ($implementComplianceControl['responsible']) {
                    $responsibleUsers = array_merge(
                        $responsibleUsers,
                        [
                            'receiver1_email' => $admins->where('id', $implementComplianceControl['responsible'])
                                ->first()['email'],
                        ]
                    );
                }
                if ($implementComplianceControl['approver']) {
                    $responsibleUsers = array_merge(
                        $responsibleUsers,
                        [
                            'receiver2_email' => $admins->where('id', $implementComplianceControl['approver'])
                                ->first()['email'],
                        ]
                    );
                }
            }
            $riskProjectOwner =  $admins->where('id', $riskRegister['project']['owner_id'])->first();
            if ($riskProjectOwner) {
                $responsibleUsers = array_merge($responsibleUsers, [
                    'project_owner_email' => $riskProjectOwner['email'],
                ]);
            }
            return $responsibleUsers;
        } catch (\Exception $th) {
            writeLog('error', 'Risk notification could not get responsible users' . $th->getMessage());
            return $th;
        }
    }

    //order the risks by who to send
    private function riskNotificationOrderByReceiver($riskNotificationDb)
    {
        try {
            //getIDs
            $riskIds = $riskNotificationDb->pluck('risk_id')->toArray();
            //get later opened risk by comparison
            $laterOpenedRisks = RiskRegister::whereIn('id', $riskIds)
                ->where('status', 'Open')->get()->pluck('id');

            //if any open risks then remove that
            if ($laterOpenedRisks->count() > 0) {
                $riskNotificationDb = $riskNotificationDb->whereNotIn('risk_id', $laterOpenedRisks);
            }
            $orderByReceiver1 = $riskNotificationDb->groupBy('receiver1_email')->toArray();
            $orderByReceiver2 = $riskNotificationDb->groupBy('receiver2_email')->toArray();
            $orderByReceiver3 = $riskNotificationDb->groupBy('project_owner_email')->toArray();
            return array_merge_recursive(
                $orderByReceiver1,
                $orderByReceiver2,
                $orderByReceiver3,
            );
        } catch (\Exception $th) {
            writeLog('error', 'Risk notification could not order by receiver' . $th->getMessage());
            return $th;
        }
    }

    private function formatAndSendRiskClosedNotifications($allNotifications, $receiverList)
    {
        try {
            foreach ($allNotifications as $receiver => $risks) {
                if ($receiver) {
                    $emailBody = [
                        'greeting' => 'Hello ' . decodeHTMLEntity(
                            ucwords($receiverList->where('email', $receiver)->first()['full_name'])
                        ),
                        'content1' => 'The below risks have been closed.<br />',
                    ];
                    //group by control project
                    $riskOrderedByProjectNames = collect($risks)->unique('risk_id')->groupBy('project_name')->toArray();

                    $content2 = collect(array_map(function ($projectName, $risks) {
                        $controlBody = $projectName ?
                            '<br /> <b style="color: #000000;">Project Name: </b> ' .
                            decodeHTMLEntity($projectName) .
                            '<br /><b style="color: #000000;">Risk Treatment: </b> Mitigate' :
                            '<br /><b style="color: #000000;">Risk Treatment: </b> Accept';

                        $riskOrderedByRiskProjectNames = collect($risks)->groupBy('risk_project_name')->toArray();

                        $projectBasedRisk = collect(array_map(function ($riskProjectName, $risks) {
                            $controlBody = '<br /><u><b style="color: #000000;">Risk Project Name</u>: </b>' .
                                decodeHTMLEntity($riskProjectName);

                            $allRisks = collect($risks)->unique('risk_id')->groupBy('project_control_name')->toArray();
                            $controlBasedRisk = collect(array_map(function ($control, $risks) {
                                $controlBody = $control ? '<br /> <b style="color: #000000;">Control: </b> ' .
                                    decodeHTMLEntity($control) : '';
                                $risks = collect($risks)->pluck('risk_name')->implode(', ');
                                $riskBody = '<br /><b style="color: #000000;">Risk Names: </b> '
                                    . decodeHTMLEntity($risks);

                                return array_merge(
                                    [
                                        $controlBody
                                    ],
                                    [
                                        $riskBody,
                                        '<br />'
                                    ]
                                );
                            }, array_keys($allRisks), array_values($allRisks)))->flatten(0)->toArray();

                            return array_merge(
                                [
                                    $controlBody
                                ],
                                [
                                    $controlBasedRisk,
                                ]
                            );
                        }, array_keys($riskOrderedByRiskProjectNames), array_values($riskOrderedByRiskProjectNames)))
                            ->flatten(0)->toArray();

                        return array_merge(
                            [
                                $controlBody
                            ],
                            [
                                '<br />',
                                $projectBasedRisk,
                                '<hr>'
                            ]
                        );
                    }, array_keys($riskOrderedByProjectNames), array_values($riskOrderedByProjectNames)))
                        ->flatten(0)->toArray();

                    $emailBody = array_merge(
                        $emailBody,
                        [
                            'content2' => collect($content2)->implode(' '),
                        ],
                        [
                            'content3' => '<b style="color: #000000;">Status: </b> Closed',
                            'content4' => 'No further action is needed.',
                        ]
                    );

                    Mail::to($receiver)->send(new RiskClose($emailBody));
                }
            }
        } catch (\Exception $th) {
            writeLog('error', 'Risk notification could not format and send notification' . $th->getMessage());
            return $th;
        }
    }
}
