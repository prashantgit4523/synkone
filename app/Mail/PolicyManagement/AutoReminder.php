<?php

namespace App\Mail\PolicyManagement;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AutoReminder extends Mailable
{
    use Queueable;
    use SerializesModels;

    public $user;
    public $campaign;
    public $acknowledgementGroup;
    public $acknowledgmentUserToken;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($acknowledgmentUserToken, $campaign, $acknowledgementGroup, $user)
    {
        $this->user = $user;
        $this->campaign = $campaign;
        $this->acknowledgementGroup = $acknowledgementGroup;
        $this->acknowledgmentUserToken = $acknowledgmentUserToken;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        if($this->campaign->campaign_type == 'awareness-campaign')
        {
            return $this->markdown('emails.policy-management.awareness-campaign-auto-reminder')->subject('Awareness course - '.$this->user->first_name.' '.$this->user->last_name);
        }
        else
        {
            return $this->markdown('emails.policy-management.auto-reminder')->subject('Policy management reminder - '.$this->campaign->name);
        }
    }
}
