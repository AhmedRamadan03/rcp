<?php

namespace App\Services;

use App\Enums\ClaimStatus;
use App\Enums\LinkType;
use App\Enums\NotificationType;
use App\Enums\PaymentType;
use App\Enums\SupervisorActions;
use App\Models\Assigning;
use App\Models\Claim;
use App\Models\PendingAction;
use App\Models\User;
use Carbon\Carbon;
use Exception;

class PendingActionService
{
    public function create(Claim $claim)
    {
        try {
            $supervisor_id = $this->getSupervisorForClaim($claim);
            $action = PendingAction::create([
                'claim_id' => $claim->id,
                'user_id' => $supervisor_id,
            ]);

            NotificationService::create([
                'title_ar' => 'يوجد مطالبة معلقة برقم '.$claim?->claim_number,
                'title_en' => 'There are Pending Claim with  number. '.$claim->claim_number,
                'description_ar' => 'هذه المطالبة مدخلة بتاريخ'.$claim?->created_at->format('Y-m-d'),
                'description_en' => 'this claim is inserted in date ('.$claim?->created_at->format('Y-m-d'),
                'regarding' => NotificationType::GENERAL->value,
                'user_id' => $supervisor_id,
                'to' => json_encode([$supervisor_id]),
                'links' => json_encode([
                    [
                        'label_ar' => 'عرض تفاصيل الطلب',
                        'label_en' => 'Open Claim Info',
                        'url' => 'claims/'. $claim?->id.'/info',
                    ],
                    [
                        'label_ar' => 'إرجاع المطالبة للمحصل',
                        'label_en' => 'return the claim to the collector',
                        'url' => 'toCollector',
                        'type' => LinkType::FRONT_END->value,
                        'model_id' => $action->id,
                        'model_class' => PendingAction::class,
                    ],
                    [
                        'label_ar' => 'إعادة إسناد المطالبة',
                        'label_en' => 'reassign claim',
                        'url' => 'toDifferentCollectorOrExternalOffice',
                        'type' => LinkType::FRONT_END->value,
                        'model_id' => $action->id,
                        'model_class' => PendingAction::class,
                    ],
                    [
                        'label_ar' => 'تحويل المطالبة للإدارة القانونية',
                        'label_en' => 'to legal department',
                        'url' => 'toLegalDepartment',
                        'type' => LinkType::FRONT_END->value,
                        'model_id' => $action->id,
                        'model_class' => PendingAction::class,
                    ],
                ]),
            ]);
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

    }

    public function toUser(PendingAction $pendingAction)
    {
        $claim = $pendingAction->claim;
        $user_id = $this->getAssigneeForClaim($claim);
        NotificationService::create([
            'title_ar' => 'تم اسناد المطالبة بالرقم '.$claim?->claim_number.' لك.',
            'title_en' => 'The Claim number. '.$claim->claim_number.' assigned to you',
            'description_ar' => 'تم اسناد المطالبة التي ادخلتها وتحمل الرقم '.$claim?->claim_number,
            'description_en' => 'claim number ('.$claim?->claim_number.'), you just created has been assigned to you automatically ',
            'regarding' => NotificationType::CLAIM_ASSIGNED->value,
            'user_id' => $user_id,
            'to' => json_encode([$user_id]),
            'links' => json_encode([
                [
                    'label_ar' => 'عرض تفاصيل الطلب',
                    'label_en' => 'Open Claim Info',
                    'url' => 'claims/'. $claim?->id.'/info',
                ],
            ]),
        ]);

        $pendingAction->status = SupervisorActions::SEND_IT_BACK->value;
        $pendingAction->save();
    }

    public function toLegal(PendingAction $pendingAction, User $user)
    {
        $claim = Claim::find($pendingAction->claim_id);
        $claim->assignees()->update(
            [
                'is_active' => 0,
            ]
        );

        Assigning::create([
            'claim_id' => $claim->id,
            'to' => $user->id,
            'supervisor_id' => $user->supervisor_id,
            'is_active' => 1,
        ]);

        NotificationService::create([
            'title_ar' => 'تم اسناد المطالبة بالرقم '.$claim?->claim_number.' لك.',
            'title_en' => 'The Claim number. '.$claim->claim_number.' assigned to you',
            'description_ar' => 'تم اسناد المطالبة التي ادخلتها وتحمل الرقم '.$claim?->claim_number,
            'description_en' => 'claim number ('.$claim?->claim_number.'), you just created has been assigned to you automatically ',
            'regarding' => NotificationType::CLAIM_ASSIGNED->value,
            'user_id' => $user->id,
            'to' => json_encode([$user->id]),
            'links' => json_encode([
                [
                    'label_ar' => 'عرض تفاصيل الطلب',
                    'label_en' => 'Open Claim Info',
                    'url' => 'claims/'. $claim?->id.'/info',
                ],
            ]),
        ]);

        //        $pendingAction->status = SupervisorActions::SEND_IT_LEGAL->value;
        //        $pendingAction->save();
    }

    public function toNewCollectorOrExternalOffice(PendingAction $pendingAction, User $user)
    {
        $claim = Claim::find($pendingAction->claim_id);
        $claim->assignees()->update(
            [
                'is_active' => 0,
            ]
        );

        Assigning::create([
            'claim_id' => $claim->id,
            'to' => $user->id,
            'supervisor_id' => $user->supervisor_id,
            'is_active' => 1,
        ]);

        NotificationService::create([
            'title_ar' => 'تم اسناد المطالبة بالرقم '.$claim?->claim_number.' لك.',
            'title_en' => 'The Claim number. '.$claim->claim_number.' assigned to you',
            'description_ar' => 'تم اسناد المطالبة التي ادخلتها وتحمل الرقم '.$claim?->claim_number,
            'description_en' => 'claim number ('.$claim?->claim_number.'), you just created has been assigned to you automatically ',
            'regarding' => NotificationType::CLAIM_ASSIGNED->value,
            'user_id' => $user->id,
            'to' => json_encode([$user->id]),
            'links' => json_encode([
                [
                    'label_ar' => 'عرض تفاصيل الطلب',
                    'label_en' => 'Open Claim Info',
                    'url' => 'claims/'. $claim?->id.'/info',
                ],
            ]),
        ]);

    }

    /**
     * Get all Claims that in Approved status and :-
     * - it has been exceeded one month
     * - it has no rejected claims
     * - it has no payment link in delay or installment type
     * - it has no pending action
     */
    public function getClaimsToBeSentToSupervisor()
    {
        return Claim::with('assignees')
            ->whereIn('status', [
                ClaimStatus::APPROVED->value,
                ClaimStatus::FOLLOW_UP->value,
            ])
//            ->where('created_at', '<=', Carbon::now()->subMonth())
            ->whereHas('messages', function ($query) {
                $query->where('created_at', '<=', Carbon::today()->subMonth());
            })
            ->doesntHave('rejections')
            ->whereDoesntHave('payments.links', function ($query) {
                $query->whereIn('type', [
                    PaymentType::DELAY_PAYMENT->value,
                    PaymentType::INSTALLMENT_PAYMENT->value,
                ]);
            })
            ->doesntHave('pendingActions')
            ->get();
    }

    public function getSupervisorForClaim(Claim $claim)
    {
        try {
            return $claim->assignees()
                ->where('is_active', 1)
                ->latest()
                ->first()
                ->toUser
                ->supervisor_id;
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }

    }

    public function getAssigneeForClaim(Claim $claim)
    {
        try {
            return $claim->assignees()
                ->where('is_active', 1)
                ->latest()
                ->first()
                ->toUser
                ->id;
        } catch (Exception $e) {
            throw new Exception($e->getMessage());
        }
    }
}
