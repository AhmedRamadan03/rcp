<?php

namespace App\BusinessLogic\Escalation;

use App\Helpers\EmailSendingHelper;
use App\Enums\EmailSending;
use App\Models\Escalation as EscalationModel;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserSetting;
use Mail;

class EscalationSendMail
{


    public function send_escalation_email()
    {
        if (!EmailSendingHelper::isCompanyEmailEnabled()) {
            return;
        }

        $allEscalations = EscalationModel::whereBetween('created_at', [now()->startOfDay(), now()])->get()->groupBy('user_id');
        foreach ($allEscalations as $user_id => $escalations) {
            $user = User::find($user_id);

            if (!EmailSendingHelper::canUserSendEmail($user->id)) {
                continue;
            }

            Mail::to($user->email)->send(new \App\Mail\EscalationMail($escalations->groupBy('type')));
        }
    }
}
