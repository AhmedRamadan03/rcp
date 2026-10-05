<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\TransactionStatusEnquiryAction;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;


class PaymentWebhookController extends Controller
{
    public function webhook(Request $request)
    {

        $event = $request->input('event');
        $chargeId = $request->input('charge_id');
        $status = $request->input('status');

        if (!$event || !$chargeId || !$status) {
            return response()->json(['message' => 'Missing required fields'], 400);
        }

        (new TransactionStatusEnquiryAction($chargeId))->execute();


        return response()->json([
            'message' => 'Webhook received'
        ]);
    }

}
