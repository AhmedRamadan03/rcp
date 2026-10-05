<?php

namespace App\Mail;

use App\Models\ClaimComment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class Reminder extends Mailable
{
    use Queueable, SerializesModels;

     public $counter;
    /**
     * Create a new message instance.
     */
    public function __construct( $counter)
    {
        $this->counter = $counter;
    }

    /**
     * Create a new message instance.
     */
   

    public function build()
    {
        return $this->view('mail.reminder');
    }
}
