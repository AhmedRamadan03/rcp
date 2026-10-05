<?php

namespace App\Mail;

use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SuperadminRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public Notification $notification)
    {
        //
    }

    /**
     * Get the message envelope.
     */

    /**
     * Get the message content definition.
     */
    public function build()
    {
        return $this->view('mail.superadmin-request');
    }
}
