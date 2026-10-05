<?php

namespace App\Mail;

use App\Models\ClaimComment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LawReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(public ClaimComment $comment)
    {
        //
    }

    /**
     * Get the message envelope.
     */
    public function build()
    {
        return $this->view('mail.law-reminder');
    }
}
