<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FailedLoginMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $resetUrl) {}

 
    public function build()
    {
        return $this->subject('Reset Your Password')
            ->view('mail.failed-login', ['user' => $this->user, 'resetUrl' => $this->resetUrl]);
    }
}
