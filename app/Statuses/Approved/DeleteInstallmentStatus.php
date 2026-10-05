<?php

namespace App\Statuses\Approved;

use App\Enums\LinkSendStatus;
use App\Enums\NotificationType;
use App\Enums\PaymentType;
use App\Statuses\Status;
use App\Enums\RequestStatus;
use App\Events\ClaimInfo\UpdateClaimInstallmentEvent;
use App\Exceptions\RequiredSupervisorApprovalException;
use App\Helpers\PaymentLinkHelper;
use App\Models\PaymentLink;
use App\Models\PaymentRequest;
use App\Models\RequestApproval;
use App\Models\StatusChangeRequest;
use App\Services\NotificationService;
use App\Services\PaymentService;
use Carbon\Carbon;
use Exception;

class DeleteInstallmentStatus implements Status
{
    public function handel(int $request_id)
    {
        try {
           
            $status_request = StatusChangeRequest::with('claim')->find($request_id);
            $claim = $status_request->claim;
            $payment = $claim->payment;
            $supervisor = auth()->user();

            if (needs_supervisor_approval('payments', 'installment_payments', $supervisor)) {

                $higher_supervisor_id = $supervisor->supervisor_id;

                if (!$higher_supervisor_id) {
                    throw new \Exception('No supervisor assigned. Cannot escalate request.');
                }

                // حول الطلب للمشرف الأعلى
                $status_request->supervisor_id = $higher_supervisor_id;
                $status_request->save();

                RequestApproval::create([
                    'user_id'     => $supervisor->id,
                    'request_id'  => $status_request->id,
                    'status'      =>  RequestStatus::APPROVED->value . '/moved_to_supervisor',
                    'notes'       => $status_request->note,
                    'approved_at' => now(),
                ]);

                NotificationService::create([
                    'title_ar'       => __('notification.delete_installment_payment_request', [], 'ar'),
                    'title_en'       => __('notification.delete_installment_payment_request', [], 'en'),
                    'description_ar' => __('notification.you_have_delete_installment_payment_request_for_claim', [], 'ar') . '( ' . $claim->claim_number . ' )  ',
                    'description_en' => __('notification.you_have_delete_installment_payment_request_for_claim', [], 'en') . '( ' . $claim->claim_number . ' )  ',
                    'regarding'      => NotificationType::PERMISSION_REQUEST->value,
                    'user_id'        => $supervisor->id,
                    'to'             => json_encode([$higher_supervisor_id]),
                    'links'          => json_encode([
                        [
                            'title_ar' => 'الطلبات المفتوحة',
                            'title_en' => 'Open requests',
                            'url'      => 'requests',
                        ],
                    ]),
                ]);
                throw new RequiredSupervisorApprovalException('this request has been sent to the next supervisor for further approval.');
                return;
            }

            $paidInstallments = PaymentLink::where('payment_id', $payment->id)
                ->where('send_status', LinkSendStatus::PAID->value)
                ->count();

            if ($paidInstallments > 0) {
                throw new Exception('Cannot delete installments because there are paid installments');
            }

            PaymentLink::where('payment_id', $payment->id)
                    ->update([
                        'send_status' => LinkSendStatus::CANCELED->value,
                    ]);

            $firstLink = PaymentLink::where('payment_id', $payment->id)
                    ->orderBy('id', 'asc')
                    ->first();
            if ($firstLink) {
                $firstLink->send_status = LinkSendStatus::NOT_SENT->value;
                $firstLink->type = PaymentType::FULL_PAYMENT->value;
                $firstLink->amount = $payment->amount;

                $firstLink->save();
            } else {
                // create new if not exist
                $firstLink = PaymentLinkHelper::createUnique(
                    paymentId: $payment->id,
                    amount: $payment->amount,
                    dueDate: Carbon::today(),
                    type: PaymentType::FULL_PAYMENT->value
                );
            }


            event(new UpdateClaimInstallmentEvent($firstLink?->payment?->claim->id));
            $status_request->request_status = RequestStatus::APPROVED->value;
            $status_request->save();
        } catch (Exception $exception) {
            throw new Exception($exception->getMessage());
        }
    }
}
