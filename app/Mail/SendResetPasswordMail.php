<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $resetUrl) {}

 
    public function build()
    {
        return $this->subject('Reset Your Password')
            ->view('mail.reset-password', ['user' => $this->user, 'resetUrl' => $this->resetUrl]);
    }
}
