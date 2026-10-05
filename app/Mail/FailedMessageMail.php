<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FailedMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public $claim;
    public $errMessage;
    /**
     * Create a new message instance.
     */
    public function __construct($claim, $errMessage)
    {
        $this->claim = $claim;
        $this->errMessage = $errMessage;
    }

    /**
     * Get the message envelope.
     */
    public function build()
    {
        return $this->view('mail.failed-message');
    }
}
