<?php

namespace App\Mail\Integration;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class Disconnect extends Mailable
{
    use Queueable, SerializesModels;

    public $admin;
    public $integration;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($admin, $integration)
    {
        $this->admin = $admin;
        $this->integration = $integration;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->markdown('emails.integration.disconnect')->subject($this->integration->name. " was disconnected");
    }
}
