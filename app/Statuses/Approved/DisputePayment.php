<?php

namespace App\Statuses\Approved;

use App\Enums\FileVisibility;
use App\Models\AdditionalFile;
use App\Statuses\Status;
use App\Enums\ClaimStatus;
use App\Enums\LinkSendStatus;
use App\Enums\NotificationType;
use App\Enums\PaymentStatus;
use App\Enums\RequestStatus;
use App\Exceptions\RequiredSupervisorApprovalException;
use App\Models\ClaimStatusHistory;
use App\Models\PaymentLink;
use App\Models\PaymentRequest;
use App\Models\RequestApproval;
use App\Models\StatusChangeRequest;
use App\Services\ClaimService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Exception;

class DisputePayment implements Status
{
    public function handel(int $request_id)
    {
        try {
            $payment_links = PaymentRequest::where('request_id', $request_id)->pluck('link_id')->toArray();
            $request = StatusChangeRequest::where('id', $request_id)->first();
            $claim = $request->claim;
            $supervisor = auth()->user();
            if (needs_supervisor_approval('claims', 'dispute_payment', $supervisor)) {
                $higher_supervisor_id = $supervisor->supervisor_id;

                if (!$higher_supervisor_id) {
                    throw new \Exception('No supervisor assigned. Cannot escalate request.');
                }

                // حول الطلب للمشرف الأعلى
                $request->supervisor_id = $higher_supervisor_id;
                $request->save();

                RequestApproval::create([
                    'user_id'     => $supervisor->id,
                    'request_id'  => $request->id,
                    'status'      => RequestStatus::APPROVED->value . '/moved_to_supervisor',
                    'notes'       => $request->note,
                    'approved_at' => now(),
                ]);

                NotificationService::create([
                    'title_ar'       => __('notification.dispute_payment_request', [], 'ar'),
                    'title_en'       => __('notification.dispute_payment_request', [], 'en'),
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
                return;
            }

            $links = PaymentLink::whereIn('id', $payment_links)->get();

            if ($request->additional_file_id) {
                AdditionalFile::where('id', $request->additional_file_id)
                    ->update(['attachment_status' => FileVisibility::PUBLISHED->value]);
            }

            foreach ($links as $link) {
                $link->send_status = LinkSendStatus::DISPUTE_PAYMENT->value;
                $link->collector_id = null;
                $link->transactions()->delete();
                $link->save();

                $payment = $link->payment;
                $payment->paid_amount = $payment->paid_amount - $link->amount > 0 ? $payment->paid_amount - $link->amount : 0;
                $payment->payment_status = PaymentStatus::PENDING->value;
                $payment->save();
            }
            $lastStatus = ClaimStatusHistory::where('claim_id', $claim->id)->latest()->first();
            if ($claim->status == ClaimStatus::PARTIAL->value) {
                $claim->status = ClaimStatus::PARTIAL->value;
            } else {
                $claim->status = $lastStatus->previous_status;
            }
            $claim->save();


            $service = new ClaimService;

            $historyData = [
                'note' => $request->note,
                'url' => $request->file?->url,
            ];
            $service->writeToClaimStatusHistory($claim->id, $lastStatus->current_status, $lastStatus->previous_status, auth()->user()->id, $historyData);

            $request->request_status = RequestStatus::APPROVED->value;
            $request->save();
        } catch (RequiredSupervisorApprovalException $e) {
            throw $e;
        } catch (Exception $exception) {
            throw new Exception($exception->getMessage());
        }
    }
}
