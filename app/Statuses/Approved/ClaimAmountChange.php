<?php

namespace App\Statuses\Approved;

use App\Statuses\Status;
use App\Enums\ClaimStatus;
use App\Enums\NotificationType;
use App\Enums\RequestStatus;
use App\Exceptions\RequiredSupervisorApprovalException;
use App\Models\ChangeClaimAmountRequest;
use App\Models\ClaimAmountHistory;
use App\Models\RequestApproval;
use App\Models\StatusChangeRequest;
use App\Services\ClaimService;
use App\Services\NotificationService;
use App\Services\PaymentService;

class ClaimAmountChange implements Status
{
    public function handel(int $request_id)
    {

        $status_request = StatusChangeRequest::find($request_id);

        $claim = $status_request->claim;
        $supervisor = auth()->user();

        if (needs_supervisor_approval('claims', 'update_claim_amount', $supervisor)) {
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
                'description_ar' => 'طلب تغيير قيمة المطالبة (' . $claim->claim_number . ') يحتاج موافقة أعلى',
                'description_en' => 'Change amount for claim (' . $claim->claim_number . ') needs higher approval',
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
        $status_request->request_status = RequestStatus::APPROVED->value;
        $status_request->save();

        $claim = $status_request->claim;
        $claim_previous_status = $claim->status;
        
        $change_amount_request = ChangeClaimAmountRequest::where('request_id', $request_id)
            ->first();
        
        $change_amount_request->status = RequestStatus::APPROVED->value;
        $change_amount_request->save();

        ClaimAmountHistory::create([
            'claim_id' => $claim->id,
            'new_amount' => $change_amount_request->new_amount,
            'current_amount' => $change_amount_request->current_amount,
            'user_id' => auth()->user()->id,
        ]);

        $claim->amount = $change_amount_request->new_amount;

        if ($claim->status !== ClaimStatus::REGISTERED->value && $claim->status !== ClaimStatus::APPROVED->value) {
            $claim->status = ClaimStatus::FOLLOW_UP->value;
        }

        $claim->is_draft = false;

        $claim->save();

        $payment = $claim->payments()->first();

        if ($payment) {
            $payment->amount = $change_amount_request->new_amount;
            $payment->save();

            (new PaymentService)->cancelPayment($payment);
        }

        $claimAmountHistory = [
            'note' => "Claim amount changed from {$change_amount_request->current_amount} to {$change_amount_request->new_amount}" 
        ];

        (new ClaimService())->writeToClaimStatusHistory($claim->id, $claim_previous_status, ClaimStatus::FOLLOW_UP->value, auth()->user()->id, $claimAmountHistory);


    }
}
