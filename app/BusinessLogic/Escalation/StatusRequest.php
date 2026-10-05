<?php

namespace App\BusinessLogic\Escalation;

use App\Enums\ClaimStatus;
use App\Enums\EscalationModelType;
use App\Enums\EscalationType;
use App\Enums\NotificationType;
use App\Enums\RequestStatus;
use App\Models\Escalation as EscalationModel;
use App\Models\StatusChangeRequest;
use App\Services\NotificationService;
use Carbon\Carbon;
use Exception;
use Mail;

class StatusRequest extends Escalation
{
    public function send_escalation()
    {
        try {
            [$to_managers] = $this->get_data();

            foreach ($to_managers as $request) {
                if (isset($request->user->supervisor->supervisor)) {
                    if (! $this->is_escalation_sent($request, $request->user->supervisor->supervisor)) {
                        $this->create_escalation($request, $request->user->supervisor->supervisor);
                    }

                }

            }
        } catch (Exception $exception) {
            dd($exception->getMessage());
        }
    }

    public function get_data()
    {

        $to_manager = StatusChangeRequest::with('user.supervisor.supervisor')->where('request_status', RequestStatus::REGISTERED->value)
            ->where('created_at', '<=', Carbon::today()->subDays(7))
            ->get();

        return [
            $to_manager,
        ];
    }

    public function is_escalation_sent($request, $user): bool
    {
        return EscalationModel::where('user_id', $user->id)
            ->where('type', EscalationType::STATUS_REQUEST->value)
            ->where('model_type', EscalationModelType::STATUS_CHANGE_REQUEST->value)
            ->where('model_id', $request->id)
            ->count() > 0;
    }

    public function create_escalation($request, $user)
    {
        if ($request->to_status == ClaimStatus::DELAY->value) {
            $notification = NotificationService::create([
                'title_ar' => __('notification.escalation_for_delay_request', [], 'ar'),
                'title_en' => __('notification.escalation_for_delay_request', [], 'en'),
                'description_ar' => __('notification.escalation_for_delay_payment_request_for_claim', [], 'ar').'( '.$request->claim->claim_number.' )  ',
                'description_en' => __('notification.escalation_for_delay_payment_request_for_claim', [], 'en').'( '.$request->claim->claim_number.' )  ',
                'regarding' => NotificationType::GENERAL->value,
                'user_id' => $user->id,
                'to' => json_encode([$user->id]),
                'links' => json_encode([
                    [
                        'title_ar' => 'الطلبات المفتوحة',
                        'title_en' => 'Open requests',
                        'url' => 'requests',
                    ],
                ]),
            ]);
        } elseif ($request->to_status == ClaimStatus::PARTIAL->value) {
            $notification = NotificationService::create([
                'title_ar' => __('notification.escalation_for_installment_plan_request', [], 'ar'),
                'title_en' => __('notification.escalation_for_installment_plan_request', [], 'en'),
                'description_ar' => __('notification.escalation_for_installment_plan_request_for_claim', [], 'ar').'( '.$request->claim->claim_number.' )  ',
                'description_en' => __('notification.escalation_for_installment_plan_request_for_claim', [], 'en').'( '.$request->claim->claim_number.' )  ',
                'regarding' => NotificationType::GENERAL->value,
                'user_id' => $user->id,
                'to' => json_encode([$user->id]),
                'links' => json_encode([
                    [
                        'title_ar' => 'الطلبات المفتوحة',
                        'title_en' => 'Open requests',
                        'url' => 'requests',
                    ],
                ]),
            ]);
        } elseif ($request->to_status == ClaimStatus::DISCOUNT->value) {
            $notification = NotificationService::create([
                'title_ar' => __('notification.escalation_for_discount_request', [], 'ar'),
                'title_en' => __('notification.escalation_for_discount_request', [], 'en'),
                'description_ar' => __('notification.escalation_for_discount_request_for_claim', [], 'ar').'( '.$request->claim->claim_number.' )  ',
                'description_en' => __('notification.escalation_for_discount_request_for_claim', [], 'en').'( '.$request->claim->claim_number.' )  ',
                'regarding' => NotificationType::GENERAL->value,
                'user_id' => $user->id,
                'to' => json_encode([$user->id]),
                'links' => json_encode([
                    [
                        'title_ar' => 'الطلبات المفتوحة',
                        'title_en' => 'Open requests',
                        'url' => 'requests',
                    ],
                ]),
            ]);
        } elseif ($request->to_status == ClaimStatus::CLAIM_AMOUNT_CHANGE->value) {
            $notification = NotificationService::create([
                'title_ar' => __('notification.escalation_for_change_amount_request', [], 'ar'),
                'title_en' => __('notification.escalation_for_change_amount_request', [], 'en'),
                'description_ar' => __('notification.escalation_for_change_amount_request_for_claim', [], 'ar').'( '.$request->claim->claim_number.' )  ',
                'description_en' => __('notification.escalation_for_change_amount_request_for_claim', [], 'en').'( '.$request->claim->claim_number.' )  ',
                'regarding' => NotificationType::GENERAL->value,
                'user_id' => $user->id,
                'to' => json_encode([$user->id]),
                'links' => json_encode([
                    [
                        'title_ar' => 'الطلبات المفتوحة',
                        'title_en' => 'Open requests',
                        'url' => 'requests',
                    ],
                ]),
            ]);
        } else {
            $notification = NotificationService::create([
                'title_ar' => __('notification.escalation_for_change_status_request', [], 'ar'),
                'title_en' => __('notification.escalation_for_change_status_request', [], 'en'),
                'description_ar' => __('notification.escalation_for_change_status_request_for_claim', [], 'ar').'( '.$request->claim->claim_number.' )  ',
                'description_en' => __('notification.escalation_for_change_status_request_for_claim', [], 'en').'( '.$request->claim->claim_number.' )  ',
                'regarding' => NotificationType::GENERAL->value,
                'user_id' => $user->id,
                'to' => json_encode([$user->id]),
                'links' => json_encode([
                    [
                        'title_ar' => 'الطلبات المفتوحة',
                        'title_en' => 'Open requests',
                        'url' => 'requests',
                    ],
                ]),
            ]);
        }

        $escalation = EscalationModel::create([
            'notification_id' => $notification->id,
            'user_id' => $user->id,
            'model_type' => EscalationModelType::STATUS_CHANGE_REQUEST->value,
            'model_id' => $request->id,
            'type' => EscalationType::STATUS_REQUEST->value,
        ]);

        // $this->send_escalation_email($escalation, $user);
        //        Mail::to($user->email)->send(new \App\Mail\EscalationMail($escalation));
    }
}
