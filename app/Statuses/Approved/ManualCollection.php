<?php

namespace App\Statuses\Approved;

use App\Statuses\Status;
use App\Actions\GenerateLetters;
use App\BusinessLogic\PaymentReminder\PaymentReminder;
use App\Enums\ClaimStatus;
use App\Enums\LinkSendStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\RequestStatus;
use App\Enums\TransactionType;
use App\Events\Payment\PaymentSuccess;
use App\Exceptions\NotAuthorizeException;
use App\Helpers\PaymentLinkHelper;
use App\Models\Claim;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\PaymentTransaction;
use App\Models\Setting;
use App\Models\StatusChangeRequest;
use App\Services\Payment\CollectorTargetSideEffect;
use Carbon\Carbon;
use Exception;

class ManualCollection implements Status
{
    public function handel(int $request_id)
    {
        $status_request = StatusChangeRequest::find($request_id);
        $claim_id = $status_request->claim_id;
        $claim = Claim::findOrFail($claim_id);

        try {
            $payment = Payment::where('claim_id', $claim_id)->first();

            $payment->links()->where('send_status', '<>', LinkSendStatus::PAID->value)->update([
                'send_status' => LinkSendStatus::CANCELED->value,
            ]);

            if ($payment->amount != $payment->paid_amount) {
                $payment_link = PaymentLinkHelper::createUnique(
                    paymentId: $payment->id,
                    amount: $payment->amount - $payment->paid_amount,
                    dueDate: Carbon::today()->format('Y-m-d')
                );

                $transaction = PaymentTransaction::create([
                    'payment_id' => $payment_link->payment_id,
                    'link_id' => $payment_link->id,
                    'send_status' => PaymentTransactionStatus::PAID->value,
                    'amount' => $payment->amount - $payment->paid_amount,
                    'response' => json_encode([]),
                    'payment_type' => TransactionType::MANUAL->value,
                    'collector_id' => $claim->assignee?->to ?? null
                ]);

                $payment_link->send_status = LinkSendStatus::PAID->value;
                $payment_link->views_count = $payment_link->views_count + 1;
                $payment_link->last_access = Carbon::today()->format('Y-m-d');
                $payment_link->collector_id = $claim->assignee?->to ?? null;

                $payment_link->save();
            }

            $payment->paid_amount = $payment->amount;
            $payment->payment_status = PaymentStatus::PAID->value;

            $payment->save();

            $claim->update([
                'status' => ClaimStatus::MANUAL_COLLECTED->value,
            ]);

            PaymentReminder::handel(Setting::paymentReminderStatus(ClaimStatus::MANUAL_COLLECTED->value), $claim_id);

            $status_request->request_status = RequestStatus::APPROVED->value;
            $status_request->save();

            if ($transaction) {
                $generateLetters = new GenerateLetters($transaction);
                $generateLetters->execute();
            }

            if (isset($payment_link)) {
                CollectorTargetSideEffect::apply(
                    $claim,
                    (int) $payment_link->amount,
                    'approved_manual_collection'
                );
            }

        } catch (Exception $exception) {
            throw new NotAuthorizeException($exception->getMessage());
        }
    }
}
