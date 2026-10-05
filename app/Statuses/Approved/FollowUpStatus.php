<?php

namespace App\Statuses\Approved;

use App\Statuses\Status;
use App\BusinessLogic\PaymentReminder\PaymentReminder;
use App\Enums\ClaimStatus;
use App\Enums\LinkSendStatus;
use App\Enums\RequestStatus;
use App\Enums\TransactionType;
use App\Models\PaymentLink;
use App\Models\Setting;
use App\Models\StatusChangeRequest;
use App\Services\TargetService;
use Exception;

class FollowUpStatus implements Status
{
    public function handel(int $request_id)
    {
        $status_request = StatusChangeRequest::find($request_id);
        $claim = $status_request->claim;
        $manual_transaction = $claim->payments->first()->transactions()->where('payment_type', TransactionType::MANUAL->value)->first();

        // Remove the manual collection
        if ($manual_transaction) {
            $payment = $manual_transaction->payment;

            $payment->paid_amount = $payment->paid_amount - $manual_transaction->amount;
            $payment->save();
            $manual_link = PaymentLink::find($manual_transaction->link_id);

            $manual_transaction->delete();
            $manual_link->send_status = LinkSendStatus::NOT_SENT->value;
            $manual_link->save();

            try {
                $targetService = new TargetService;

                $targetService->removeFromAchieveTarget($claim->assignee->to, $manual_link->amount);
            } catch (Exception $exception) {
            }

        }

        $claim->status = ClaimStatus::FOLLOW_UP->value;
        $claim->is_close = 0;
        $claim->save();

        PaymentReminder::handel(Setting::paymentReminderStatus(ClaimStatus::FOLLOW_UP->value), $claim->id);

        $status_request->request_status = RequestStatus::APPROVED->value;
        $status_request->save();

    }
}
