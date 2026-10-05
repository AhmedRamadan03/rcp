<?php

namespace App\Services;

use App\BusinessLogic\Escalation\StatusRequest;
use App\Enums\ClaimStatus;
use App\Enums\FileVisibility;
use App\Enums\NotificationType;
use App\Enums\PermissionRequestsStatus;
use App\Enums\RequestStatus;
use App\Events\ClaimInfo\UpdateClaimInstallmentEvent;
use App\Exceptions\RequiredSupervisorApprovalException;
use App\Models\AdditionalFile;
use App\Models\StatusChangeRequest;
use App\Statuses\Approved\{
    BackToOriginalStatus, ClaimAmountChange, ClosedStatus, CreditedStatus,
    DefaultOnPaymentStatus, DelayPaymentStatus, DiscountStatusApprove,
    CollectedOutSystemStatus, DeleteInstallmentStatus, FollowUpStatus, LawFirm, ManualCollection,
    ManualCollectionForInstallment, PartialPaymentStatus, DisputePayment,
    SendDischargeLetterStatus, GenerateDischargeLetterStatus,GenerateVoucherLetterStatus,
    SendVoucherLetterStatus
};
use App\Statuses\Rejected\{RejectClaimAmountChange};
use Illuminate\Support\Facades\DB;




class RequestsService
{
    const ApproveRequest = [
        ClaimStatus::PARTIAL->value => PartialPaymentStatus::class,
        ClaimStatus::DELETE_INSTALLMENT->value => DeleteInstallmentStatus::class,
        ClaimStatus::DELAY->value => DelayPaymentStatus::class,
        ClaimStatus::MANUAL_COLLECTED->value => ManualCollection::class,
        ClaimStatus::CREDITED->value => CreditedStatus::class,
        ClaimStatus::CLOSED->value => ClosedStatus::class,
        ClaimStatus::COLLECTED_OUT_OF_SYSTEM->value => CollectedOutSystemStatus::class,
        ClaimStatus::FOLLOW_UP->value => FollowUpStatus::class,
        ClaimStatus::DEFAULT_IN_PAYMENT->value => DefaultOnPaymentStatus::class,
        ClaimStatus::DISCOUNT->value => DiscountStatusApprove::class,
        ClaimStatus::CLAIM_AMOUNT_CHANGE->value => ClaimAmountChange::class,
        ClaimStatus::LAW_FIRM->value => LawFirm::class,
        ClaimStatus::MANUAL_COLLECTED_FOR_INSTALLMENT->value => ManualCollectionForInstallment::class,
        ClaimStatus::DISPUTE_PAYMENT->value => DisputePayment::class,
        PermissionRequestsStatus::SEND_DISCHARGE_LETTER->value => SendDischargeLetterStatus::class,
        PermissionRequestsStatus::GENERATE_DISCHARGE_LETTER->value => GenerateDischargeLetterStatus::class,
        PermissionRequestsStatus::GENERATE_VOUCHER_LETTER->value => GenerateVoucherLetterStatus::class,
        PermissionRequestsStatus::SEND_VOUCHER_LETTER->value => SendVoucherLetterStatus::class,
        PermissionRequestsStatus::GO_BACK_TO_ORIGINAL_STATUS->value => BackToOriginalStatus::class
    ];

    const RejectRequest = [
        ClaimStatus::CLAIM_AMOUNT_CHANGE->value => RejectClaimAmountChange::class
    ];

    public function list($filters = [], $paginate = 10)
    {
        return $this->buildFilteredQuery($filters)->paginate($paginate);
    }

    public function show($request_id)
    {
        try {
            $status_request = StatusChangeRequest::with(['claim'])->findOrFail($request_id);

            return $status_request;

        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }

    }
    public function reject($request_id, $requestData)
    {
        try {
            $status_request = StatusChangeRequest::findOrFail($request_id);

            if ($status_request->request_status !== RequestStatus::REGISTERED->value) {
                throw new \Exception('This request has already been processed.');
            }

            $status_request->update([
                'request_status' => RequestStatus::REJECTED->value,
                'reject_reason' => $requestData['reject_reason'],
            ]);

            if ($status_request->additional_file_id) {
                AdditionalFile::where('id', $status_request->additional_file_id)
                    ->update(['attachment_status' => FileVisibility::UNPUBLISHED->value]);
            }


            if (isset(self::RejectRequest[$status_request->to_status])) {
                (new (self::RejectRequest[$status_request->to_status]))->handel($request_id);
            }

            $claim = $status_request->claim;

            $notification = NotificationService::create([
                'title_ar' => __('notification.notice_for_request_rejection', [], 'ar'),
                'title_en' => __('notification.notice_for_request_rejection', [], 'en'),
                'description_ar' => __('notification.your_request_has_been_rejected_for_claim', [], 'ar').'( '.$claim->claim_number.' )  '.__('notification.rejection reason', [], 'ar').'( '.$status_request->reject_reason.' )  ',
                'description_en' => __('notification.your_request_has_been_rejected_for_claim', [], 'en').'( '.$claim->claim_number.' )  '.__('notification.rejection reason', [], 'en').'( '.$status_request->reject_reason.' )  ',
                'regarding' => NotificationType::GENERAL->value,
                'user_id' => $status_request->user_id,
                'to' => json_encode([$status_request->user_id]),
                'links' => json_encode([
                    [
                        'title_ar' => 'الطلبات المفتوحة',
                        'title_en' => 'Open requests',
                        'url' => 'requests',
                    ],
                ]),
            ]);
        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }

    }

    public function cancel($request_id, $requestData)
    {
        try {
            $status_request = StatusChangeRequest::findOrFail($request_id);
            if($status_request->request_status == RequestStatus::CANCELLED->value){
                throw new \Exception('Request already cancelled');
            }
            $status_request->update([
                'request_status' => RequestStatus::CANCELLED->value,
                'reject_reason' => $requestData['cancel_reason'],
            ]);

            if ($status_request->additional_file_id) {
                AdditionalFile::where('id', $status_request->additional_file_id)
                    ->update(['attachment_status' => FileVisibility::UNPUBLISHED->value]);
            }

            $claim = $status_request->claim;

            $notification = NotificationService::create([
                'title_ar' => __('notification.notice_for_request_rejection', [], 'ar'),
                'title_en' => __('notification.notice_for_request_rejection', [], 'en'),
                'description_ar' => __('notification.your_request_has_been_rejected_for_claim', [], 'ar').'( '.$claim->claim_number.' )  '.__('notification.rejection reason', [], 'ar').'( '.$status_request->reject_reason.' )  ',
                'description_en' => __('notification.your_request_has_been_rejected_for_claim', [], 'en').'( '.$claim->claim_number.' )  '.__('notification.rejection reason', [], 'en').'( '.$status_request->reject_reason.' )  ',
                'regarding' => NotificationType::GENERAL->value,
                'user_id' => $status_request->user_id,
                'to' => json_encode([$status_request->user_id]),
                'links' => json_encode([
                    [
                        'title_ar' => 'الطلبات المفتوحة',
                        'title_en' => 'Open requests',
                        'url' => 'requests',
                    ],
                ]),
            ]);
        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }
    }


    public function accept($request_id)
    {
        try {

            $status_request = StatusChangeRequest::findOrFail($request_id);

            if ($status_request->request_status !== RequestStatus::REGISTERED->value) {
                throw new \Exception('Request already processed');
            }

            $backToOriginalRequest = $status_request->request_type == PermissionRequestsStatus::GO_BACK_TO_ORIGINAL_STATUS->value;

            $handlerKey = $backToOriginalRequest
                ? $status_request->request_type
                : $status_request->to_status;

            $handlerClass = self::ApproveRequest[$handlerKey] ?? null;

            if (!$handlerClass) {
                throw new \Exception('No approval handler found');
            }

            (new $handlerClass)->handel($request_id);

            $status_request->refresh();

            if ($status_request->request_status == RequestStatus::APPROVED->value) {

                NotificationService::create([
                    'title_ar' => __('notification.notice_for_request_acceptance', [], 'ar'),
                    'title_en' => __('notification.notice_for_request_acceptance', [], 'en'),
                    'description_ar' => __('notification.your_request_has_been_accepted_for_claim', [], 'ar') . '( ' . $status_request->claim->claim_number . ' )  ',
                    'description_en' => __('notification.your_request_has_been_accepted_for_claim', [], 'en') . '( ' . $status_request->claim->claim_number . ' )  ',
                    'regarding' => NotificationType::PERMISSION_REQUEST->value,
                    'user_id' => $status_request->user_id,
                    'to' => json_encode([$status_request->user_id]),
                    'links' => json_encode([
                        [
                            'title_ar' => 'الطلبات المفتوحة',
                            'title_en' => 'Open requests',
                            'url' => 'requests',
                        ],
                    ]),
                ]);
                event(new UpdateClaimInstallmentEvent($status_request->claim_id));
            }

        } catch (RequiredSupervisorApprovalException $e) {
            throw $e;
        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }

    }

    public function escalate($request_id)
    {
        try {
            $status_request = StatusChangeRequest::find($request_id);
            $supervisor = auth()->user()->supervisor;
            $status_request->update([
                'claim_id' => $status_request->claim_id,
                'user_id' => $status_request->user_id,
                'supervisor_id' => auth()->user()->supervisor_id,
            ]);

            (new StatusRequest)->create_escalation($status_request, $supervisor);
        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }

    }


    public function exportQuery(array $filters = [], ?int $userId = null)
    {
        return $this->buildFilteredQuery($filters, $userId);
    }


    public static function cancelPendingRequest($claim_id, $to_status, $reject_reason = null)
    {
        StatusChangeRequest::where('claim_id', $claim_id)
        ->where('to_status', $to_status)
        ->where('request_status', RequestStatus::REGISTERED->value)
        ->update([
            'request_status' => RequestStatus::CANCELLED->value,
            'reject_reason' => $reject_reason ?? null,
        ]);
    }


    private function buildFilteredQuery(array $filters = [], ?int $userId = null)
    {
        $userId = $userId ?? auth()->id();

        return StatusChangeRequest::with(['file', 'changeAmount', 'law.user', 'claim', 'user', 'supervisor'])
            ->orderBy('created_at', 'DESC')

            ->where(function ($query) use ($userId) {
                $query->orWhere('user_id', $userId)
                    ->orWhere('supervisor_id', $userId)
                    ->orWhereHas('approvals', function ($query) use ($userId) {
                        $query->where('user_id', $userId);
                    });
            })
            
            // ids
            ->when(isset($filters['ids']), function ($qu) use ($filters) {
                $qu->whereIn('id', $filters['ids']);
            })

            // created_at range
            ->when(
                isset($filters['created_at_from']) && isset($filters['created_at_to']),
                function ($query) use ($filters) {
                    $query->whereBetween('created_at', [
                        $filters['created_at_from'],
                        $filters['created_at_to'],
                    ]);
                }
            )

            // claim_id
            ->when(isset($filters['claim_id']), function ($qu) use ($filters) {
                $qu->where('claim_id', $filters['claim_id']);
            })

            // from_status
            ->when(isset($filters['from_status']), function ($qu) use ($filters) {
                $qu->whereIn('from_status', $filters['from_status']);
            })

            // to_status
            ->when(isset($filters['to_status']), function ($qu) use ($filters) {
                $qu->whereIn('to_status', $filters['to_status']);
            })
            ->when(isset($filters['status']), function ($qu) use ($filters) {
                $qu->whereIn('request_status', $filters['status']);
            })

            // note
            ->when(isset($filters['note']), function ($qu) use ($filters) {
                $qu->where('note', 'ILIKE', '%' . $filters['note'] . '%');
            })

            // claim_number
            ->when(isset($filters['claim_number']), function ($qu) use ($filters) {
                $qu->whereHas('claim', function ($q) use ($filters) {
                    $q->where('claim_number', 'ILIKE', '%' . $filters['claim_number'] . '%');
                });
            })

            // requested_by
            ->when(isset($filters['requested_by']), function ($qu) use ($filters) {
                $qu->whereHas('user', function ($q) use ($filters) {
                    $q->where('name', 'ILIKE', '%' . $filters['requested_by'] . '%');
                });
            })

            // supervisor_name
            ->when(isset($filters['supervisor_name']), function ($qu) use ($filters) {
                $qu->whereHas('supervisor', function ($q) use ($filters) {
                    $q->where('name', 'ILIKE', '%' . $filters['supervisor_name'] . '%');
                });
            })

            // search
            ->when(isset($filters['search']), function ($query) use ($filters) {
                $query->where(function ($query) use ($filters) {
                    $query->whereHas('claim', function ($query) use ($filters) {
                        $query->where('claim_number', 'ILIKE', '%' . $filters['search'] . '%')
                            ->orWhere('id', 'ILIKE', '%' . $filters['search'] . '%')
                            ->orWhere('from_status', 'ILIKE', '%' . $filters['search'] . '%')
                            ->orWhere('to_status', 'ILIKE', '%' . $filters['search'] . '%')
                            ->orWhere('request_status', 'ILIKE', '%' . $filters['search'] . '%');
                    })->orWhereHas('user', function ($query) use ($filters) {
                        $query->where('name', 'ILIKE', '%' . $filters['search'] . '%');
                    })->orWhereHas('supervisor', function ($query) use ($filters) {
                        $query->where('name', 'ILIKE', '%' . $filters['search'] . '%');
                    });
                });
            });
    }

}
