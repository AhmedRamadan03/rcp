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

class BackToOriginalStatus implements Status
{
    public function handel(int $request_id)
    {
        try {

            $status_request = StatusChangeRequest::with('claim')->find($request_id);
            $claim = $status_request->claim;

            $supervisor = auth()->user();

            if (needs_supervisor_approval('claim_status', 'back_claim_to_original_status', $supervisor)) {
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
                return;
            }

            $status_request->request_status = RequestStatus::APPROVED->value;
            $status_request->save();


            $claim_previous_status = $claim->status;
            $claim_new_status = $status_request->to_status;


            (new ClaimService)->writeToClaimStatusHistory($claim->id, $claim_previous_status, $claim_new_status, auth()->user()->id);
            $claim->is_close = 0;
            $claim->status = $claim_new_status;
            $claim->save();

        } catch (RequiredSupervisorApprovalException $exception) {
            throw $exception;
        } catch (Exception $exception) {
            throw new Exception($exception->getMessage());
        }
    }
}
