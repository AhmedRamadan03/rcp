<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewUserMail extends Mailable implements ShouldQueue
{
    use SerializesModels;

    public function __construct(public User $user) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('user.hi_user'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.user-created',
            with: [
                'user' => $this->user,
            ],
        );
    }
}
