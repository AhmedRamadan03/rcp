<?php

namespace App\Statuses\Approved;

use App\Enums\NotificationType;
use App\Statuses\Status;
use App\Enums\RequestStatus;
use App\Exceptions\RequiredSupervisorApprovalException;
use App\Models\RequestApproval;
use App\Models\StatusChangeRequest;
use App\Services\ClaimService;
use App\Services\NotificationService;
use Exception;


class SendVoucherLetterStatus implements Status
{
    public function handel(int $request_id)
    {
        try {
            $status_request = StatusChangeRequest::with('paymentRequest')->find($request_id);
            $claim = $status_request->claim;
            $supervisor = auth()->user();

            if (needs_supervisor_approval('claims', 'generate_voucher_letter', $supervisor)) {

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
                    'title_ar'       => __('notification.send_voucher_generated', [], 'ar'),
                    'title_en'       => __('notification.send_voucher_generated', [], 'en'),
                    'description_ar' => 'طلب إنشاء قسيمة مالية (' . $claim->claim_number . ') يحتاج موافقة أعلى',
                    'description_en' => 'Request for sending voucher letter (' . $claim->claim_number . ') needs higher approval',
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
            $linkId = $status_request->paymentRequest->first()->link_id;

            (new ClaimService)->getVoucher($linkId);

            $status_request->request_status = RequestStatus::APPROVED->value;
            $status_request->save();
        } catch (RequiredSupervisorApprovalException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            throw new Exception($exception->getMessage());
        }
    }
}
