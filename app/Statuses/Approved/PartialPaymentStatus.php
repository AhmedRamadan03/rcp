<?php

namespace App\Statuses\Approved;

use App\Enums\NotificationType;
use App\Statuses\Status;
use App\Enums\RequestStatus;
use App\Exceptions\RequiredSupervisorApprovalException;
use App\Models\PaymentRequest;
use App\Models\RequestApproval;
use App\Models\StatusChangeRequest;
use App\Services\NotificationService;
use App\Services\PaymentService;
use Exception;

class PartialPaymentStatus implements Status
{
    public function handel(int $request_id)
    {
        try {
            $payments_link = PaymentRequest::where('request_id', $request_id)
                ->get();
            $status_request = StatusChangeRequest::find($request_id);
            $claim = $status_request->claim;
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
                    'title_ar'       => __('notification.status_change_request', [], 'ar'),
                    'title_en'       => __('notification.status_change_request', [], 'en'),
                    'description_ar' => __('notification.you_have_change_status_request_for_claim', [], 'ar') . '( ' . $claim->claim_number . ' )  ',
                    'description_en' => __('notification.you_have_change_status_request_for_claim', [], 'en') . '( ' . $claim->claim_number . ' )  ',
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

            $installments_add = [];
            $payment_id = $payments_link->first()->payment_id;

            foreach ($payments_link as $link) {
                $installment = [];
                $installment['id'] = $link->link_id;
                $installment['amount'] = $link->link_amount;
                $installment['order'] = $link->link_order;
                $installment['status'] = $link->link_status;
                $installment['date'] = $link->link_date;
                $installments_add[] = $installment;
            }
            (new PaymentService)->createNewInstallments($installments_add, $payment_id);
            $status_request->request_status = RequestStatus::APPROVED->value;
            $status_request->save();
        } catch (Exception $exception) {
            throw new Exception($exception->getMessage());
        }
    }
}
