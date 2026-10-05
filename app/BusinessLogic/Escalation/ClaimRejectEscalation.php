<?php

namespace App\BusinessLogic\Escalation;

use App\Enums\EscalationModelType;
use App\Enums\EscalationType;
use App\Enums\NotificationType;
use App\Enums\RejectStatus;
use App\Models\Escalation as EscalationModel;
use App\Models\RejectedClaim;
use App\Services\NotificationService;
use Carbon\Carbon;

class ClaimRejectEscalation extends Escalation
{
    public function send_escalation()
    {
        //        [$to_supervisors, $to_managers] = $this->get_data();
        [$to_supervisors] = $this->get_data();

        foreach ($to_supervisors as $rejected_claim) {
            if (isset($rejected_claim->claim->assignees()
                ->where('is_active', 1)
                ->latest()
                ->first()->toUser->supervisor)) {
                if (! $this->is_escalation_sent($rejected_claim, $rejected_claim->claim->assignees()
                    ->where('is_active', 1)
                    ->latest()
                    ->first()->toUser->supervisor)) {
                    $this->create_escalation($rejected_claim, $rejected_claim->claim->assignees()
                        ->where('is_active', 1)
                        ->latest()
                        ->first()->toUser->supervisor);
                }

            }

        }

        //        foreach ($to_managers as $rejected_claim) {
        //            if (isset($rejected_claim->claim->assignees()
        //                    ->where("is_active", 1)
        //                    ->latest()
        //                    ->first()->toUser->supervisor->supervisor)) {
        //                if (!$this->is_escalation_sent($rejected_claim, $rejected_claim->claim->assignees()
        //                    ->where("is_active", 1)
        //                    ->latest()
        //                    ->first()->toUser->supervisor->supervisor)) {
        //                    $this->create_escalation($rejected_claim, $rejected_claim->claim->assignees()
        //                        ->where("is_active", 1)
        //                        ->latest()
        //                        ->first()->toUser->supervisor->supervisor);
        //                }
        //
        //            }
        //
        //        }
    }

    public function get_data()
    {
        $to_supervisor = RejectedClaim::with('user', 'claim.assignees')->where('status', RejectStatus::NOT_SEEN->value)
            ->where('created_at', '<=', Carbon::today()->subDays(2))
            ->get();

        //        $to_manager = RejectedClaim::with('user', 'claim.assignees')->where("status", RejectStatus::NOT_SEEN->value)
        //            ->where("created_at", "<=", Carbon::today()->subDays(7))
        //            ->get();

        //        var_dump($to_manager);
        return [
            $to_supervisor,
            //            $to_manager
        ];
    }

    public function is_escalation_sent($rejected_claim, $user): bool
    {
        return EscalationModel::where('user_id', $user->id)
            ->where('type', EscalationType::CLAIM_REJECT->value)
            ->where('model_type', EscalationModelType::REJECTED_CLAIM->value)
            ->where('model_id', $rejected_claim->id)
            ->count() > 0;
    }

    public function create_escalation($rejected_claim, $user)
    {
        $notification = NotificationService::create([
            'title_ar' => __('notification.escalation_for_client_request', [], 'ar'),
            'title_en' => __('notification.escalation_for_client_request', [], 'en'),
            'description_ar' => __('notification.escalation_for_client_request_for_claim', [], 'ar').'( '.$rejected_claim->claim->claim_number.' )  ',
            'description_en' => __('notification.escalation_for_client_request_for_claim', [], 'en').'( '.$rejected_claim->claim->claim_number.' )  ',
            'regarding' => NotificationType::GENERAL->value,
            'user_id' => $user->id,
            'to' => json_encode([$user->id]),
            'links' => json_encode([
                [
                    'title_ar' => 'الطلبات المفتوحة',
                    'title_en' => 'Open requests',
                    'url' => 'client-requests',
                ],
            ]),
        ]);

        $escalation = EscalationModel::create([
            'notification_id' => $notification->id,
            'user_id' => $user->id,
            'model_type' => EscalationModelType::REJECTED_CLAIM->value,
            'model_id' => $rejected_claim->id,
            'type' => EscalationType::CLAIM_REJECT->value,
        ]);

        // $this->send_escalation_email($escalation, $user);
        //        Mail::to($user->email)->send(new \App\Mail\EscalationMail($escalation));
    }
}
