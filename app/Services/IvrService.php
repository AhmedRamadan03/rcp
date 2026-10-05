<?php

namespace App\Services;

use App\Models\Ivr;

class IvrService
{
    public function reCall(Ivr $ivr)
    {
        $smsService = new SmsService;

        $response = $smsService->ivr($ivr->paymentLink, $ivr->audio_id);

        IVR::create([
            'payment_link_id' => $ivr->payment_link_id,
            'status' => $response['status'],
            'callId' => $response['callId'],
            'referenceId' => $response['referenceId'],
            'audio_id' => $ivr->audio_id,
            'retry_number' => $ivr->retry_number + 1,
            'type' => $ivr->type,
        ]);
    }
}
