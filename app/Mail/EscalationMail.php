<?php

namespace App\Mail;

use App\Models\Escalation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EscalationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $escalations;
    /**
     * Create a new message instance.
     */
    public function __construct( $escalations)
    {
        $this->escalations = $escalations;
    }

    /**
     * Get the message envelope.
     */
    public function build()
    {
        return $this->view('mail.escalation');
    }
}
