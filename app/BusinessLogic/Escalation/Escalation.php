<?php

namespace App\BusinessLogic\Escalation;

use App\Models\Escalation as EscalationModel;
use App\Models\User;
use Mail;

abstract class Escalation
{
    abstract public function send_escalation();

    abstract public function get_data();

    public function send_escalation_email(EscalationModel $escalation, User $user)
    {
        try {
            Mail::to($user->email)->send(new \App\Mail\EscalationMail($escalation));
        } catch (\Exception $e) {
            dd($e->getMessage());
        }

    }
}
