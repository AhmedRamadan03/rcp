<?php

namespace App\Statuses\Approved;

use App\Enums\FileVisibility;
use App\Models\AdditionalFile;
use App\Statuses\Status;
use App\BusinessLogic\PaymentReminder\PaymentReminder;
use App\Enums\ClaimStatus;
use App\Enums\MessageTemplates;
use App\Enums\NotificationType;
use App\Enums\RequestStatus;
use App\Exceptions\RequiredSupervisorApprovalException;
use App\Models\LawDepartmentClaim;
use App\Models\RequestApproval;
use App\Models\Setting;
use App\Models\StatusChangeRequest;
use App\Services\AssigningService;
use App\Services\ClaimService;
use App\Services\MessageService;
use App\Services\NotificationService;
use App\Services\SmsService;
use Carbon\Carbon;

class LawFirm implements Status
{
    public function handel(int $request_id)
    {
        $status_request = StatusChangeRequest::find($request_id);
        $claim = $status_request->claim;
        $supervisor = auth()->user();

        if (needs_supervisor_approval('claim_status', 'transfer_to_legal', $supervisor)) {

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
                'description_ar' => 'طلب تحويل المطالبة (' . $claim->claim_number . ') يحتاج موافقة أعلى',
                'description_en' => 'Transfer request for claim (' . $claim->claim_number . ') needs higher approval',
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

        // ✅ granted = 1 و with_approve = 0 → ينفذ
        $previous_status = $claim->status;
        $law_request = LawDepartmentClaim::where('request_id', $request_id)
            ->where('claim_id', $claim->id)
            ->first();

        $claim->status = ClaimStatus::LAW_FIRM->value;
        $claim->save();

        (new AssigningService)->create([
            'to'       => [$law_request->user_id],
            'claims_id' => [$claim->id],
            'supervisor_id' => $supervisor->id,
        ]);

        if ($status_request->additional_file_id) {
            AdditionalFile::where('id', $status_request->additional_file_id)
                ->update(['attachment_status' => FileVisibility::PUBLISHED->value]);
        }

        $messageService = new MessageService;
        $message = $messageService->convertTemplate(MessageTemplates::TRANSFER_TO_LAWYER->value, $claim->id);
        (new SmsService)->sendSms($claim->debtor_phone, $message, $claim->id, MessageTemplates::TRANSFER_TO_LAWYER->value, template_model_id: $claim->id);

        NotificationService::create([
            'title_ar'       => 'تم اضافة طلب جديد',
            'title_en'       => 'New claim added to the list',
            'description_ar' => 'تم تحويل المطالبة برقم ' . $claim->claim_number . ' و بمبلغ ' . $claim->amount . ' للإدارة القانونية.',
            'description_en' => 'Claim ' . $claim->claim_number . ' transferred to legal department with amount ' . $claim->amount . ' SAR',
            'regarding'      => NotificationType::LAW->value,
            'user_id'        => $supervisor->id,
            'to'             => json_encode(array_map('intval', [$law_request->user_id])),
            'links'          => json_encode([
                [
                    'label_ar' => 'عرض تفاصيل الطلب',
                    'label_en' => 'Open Claim Info',
                    'url'      => 'claims/' . $claim?->id . '/info',
                ],
            ]),
        ]);

        PaymentReminder::handel(Setting::paymentReminderStatus(ClaimStatus::LAW_FIRM->value), $claim->id);

        $status_request->request_status = RequestStatus::APPROVED->value;
        $status_request->save();

        RequestApproval::create([
            'user_id'     => $supervisor->id,
            'request_id'  => $status_request->id,
            'status'      => RequestStatus::APPROVED->value,
            'notes'       => $status_request->note,
            'approved_at' => now(),
        ]);

        (new ClaimService)->writeToClaimStatusHistory(
            $claim->id,
            $previous_status,
            ClaimStatus::LAW_FIRM->value,
            $status_request->user_id
        );
    }
}
