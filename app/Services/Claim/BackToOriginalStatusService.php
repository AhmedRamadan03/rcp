<?php

namespace App\Services\Claim;

use App\Enums\ClaimInsertionType;
use App\Enums\ClaimStatus;
use App\Enums\NotificationType;
use App\Enums\PermissionRequestsStatus;
use App\Enums\RequestStatus;
use App\Models\Claim;
use App\Models\ClaimStatusHistory;
use App\Models\StatusChangeRequest;
use App\Models\User;
use App\Services\ClaimService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;

class BackToOriginalStatusService
{
    public function __construct(
        private readonly ClaimService $claimService,
    ) {}

    public function execute(
        Claim $claim,
        ClaimActionOptions $options,
        ?int $actingUserId = null
    ): bool {
        $actingUserId ??= auth()->id();
        $user = auth()->user();

        if (!$actingUserId) {
            throw new \RuntimeException(
                'No authenticated user found.'
            );
        }

        return DB::transaction(function () use (
            $claim,
            $options,
            $actingUserId,
            $user
        ) {
            $this->validateClaim($claim);

            $lastStatus = $this->getLastStatus($claim);

            if ($options->requiresApproval) {
                $needsApproval = needs_supervisor_approval('claim_status','back_claim_to_original_status',$user);
                if ($needsApproval) {
                    if ($this->createApprovalRequest(
                        $claim,
                        $lastStatus,
                        $actingUserId,
                        $options
                    )) {
                        return false;
                    }
                }
            }

            $this->restoreStatus(
                $claim,
                $lastStatus,
                $actingUserId
            );

            return true;
        });
    }


    private function validateClaim(Claim $claim): void
    {
        $allowedStatuses = [
            ClaimStatus::LAW_FIRM->value,
            ClaimStatus::CLOSED->value,
            ClaimStatus::MANUAL_COLLECTED->value,
            ClaimStatus::DEFAULT_IN_PAYMENT->value,
            ClaimStatus::COLLECTED_OUT_OF_SYSTEM->value,
        ];

        if (!in_array($claim->status, $allowedStatuses, true)) {
            throw new \RuntimeException(
                'You Can Not Go Back To Original Status'
            );
        }

        if ($claim->insertion_type === ClaimInsertionType::BACKLOG->value) {
            throw new \RuntimeException(
                'You Can Not Go Back To Original Status For Backlog Claims'
            );
        }
    }


    private function getLastStatus(Claim $claim): ClaimStatusHistory
    {
        return ClaimStatusHistory::where('claim_id', $claim->id)
            ->latest('id')
            ->firstOrFail();
    }


    private function createApprovalRequest(Claim $claim,ClaimStatusHistory $lastStatus,int $actingUserId,ClaimActionOptions $options): bool {
        $user = User::find($actingUserId);

        if (!$user) {
            throw new \RuntimeException(
                'No authenticated user found.'
            );
        }

        if (!$user->supervisor_id) {
            throw new \Exception(
                __('request.supervisor_not_assigned')
            );
        }

        $hasRequest = StatusChangeRequest::where('claim_id', $claim->id)
            ->where(
                'request_type',
                PermissionRequestsStatus::GO_BACK_TO_ORIGINAL_STATUS->value
            )
            ->where(
                'request_status',
                RequestStatus::REGISTERED->value
            )
            ->where('to_status', $lastStatus->previous_status)
            ->where('from_status', $lastStatus->current_status)
            ->exists();

        if ($hasRequest) {
            throw new \Exception(
                'There is already a request to go back to original status'
            );
        }

        StatusChangeRequest::create([
            'claim_id' => $claim->id,
            'user_id' => $actingUserId,
            'supervisor_id' => $user->supervisor_id,
            'from_status' => $lastStatus->current_status,
            'to_status' => $lastStatus->previous_status,
            'request_status' => RequestStatus::REGISTERED->value,
            'request_type' => PermissionRequestsStatus::GO_BACK_TO_ORIGINAL_STATUS->value,
        ]);

        if ($options->sendApprovalNotification) {
            $this->sendApprovalNotification($claim, $user);
        }

        return true;
    }



    private function sendApprovalNotification(Claim $claim,$user): void {
        NotificationService::create([
            'title_ar' => __('notification.status_change_request', [], 'ar'),
            'title_en' => __('notification.status_change_request', [], 'en'),

            'description_ar' =>
                'طلب تغيير حالة المطالبة (' .
                $claim->claim_number .
                ') يحتاج موافقة أعلى',

            'description_en' =>
                'Status change request for claim (' .
                $claim->claim_number .
                ') needs higher approval',

            'regarding' => NotificationType::PERMISSION_REQUEST->value,

            'user_id' => $user->id,

            'to' => json_encode([
                $user->supervisor_id
            ]),

            'links' => json_encode([
                [
                    'title_ar' => 'الطلبات المفتوحة',
                    'title_en' => 'Open requests',
                    'url' => 'requests',
                ],
            ]),
        ]);
    }


    private function restoreStatus(Claim $claim,ClaimStatusHistory $lastStatus,int $actingUserId): void {
        $previousStatus = $claim->status;

        $claim->update([
            'status' => $lastStatus->previous_status,
            'is_close' => 0,
        ]);

        $this->claimService->writeToClaimStatusHistory(
            $claim->id,
            $previousStatus,
            $lastStatus->previous_status,
            $actingUserId
        );
    }
}