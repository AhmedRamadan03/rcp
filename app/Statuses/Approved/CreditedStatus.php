<?php

namespace App\Statuses\Approved;

use App\Statuses\Status;
use App\BusinessLogic\PaymentReminder\PaymentReminder;
use App\Enums\ClaimStatus;
use App\Enums\NotificationType;
use App\Enums\RequestStatus;
use App\Exceptions\RequiredSupervisorApprovalException;
use App\Models\Claim;
use App\Models\RequestApproval;
use App\Models\Setting;
use App\Models\StatusChangeRequest;
use App\Services\NotificationService;

class CreditedStatus implements Status
{
    public function handel(int $request_id)
    {
        $status_request = StatusChangeRequest::find($request_id);
        $supervisor = auth()->user();
         $claim = $status_request->claim;
          if (needs_supervisor_approval('claim_status', 'collected_credited', $supervisor)) {
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
                'status'      => RequestStatus::APPROVED->value . '/moved_to_supervisor',
                'notes'       => $status_request->note,
                'approved_at' => now(),
            ]);

            NotificationService::create([
                'title_ar'       => __('notification.status_change_request', [], 'ar'),
                'title_en'       => __('notification.status_change_request', [], 'en'),
                'description_ar' => 'طلب تغيير حالة المطالبة (' . $claim->claim_number . ') يحتاج موافقة أعلى',
                'description_en' => 'Status change request for claim (' . $claim->claim_number . ') needs higher approval',
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
            return ;
        }

        $claim_id = $status_request->claim_id;
        Claim::findOrFail($claim_id)->update([
            'status' => ClaimStatus::CREDITED->value,
        ]);
        PaymentReminder::handel(Setting::paymentReminderStatus(ClaimStatus::CREDITED->value), $claim_id);

        $status_request->request_status = RequestStatus::APPROVED->value;
        $status_request->save();
    }
}
