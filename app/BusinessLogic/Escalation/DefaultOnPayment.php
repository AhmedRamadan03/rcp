<?php

namespace App\BusinessLogic\Escalation;

use App\Enums\EscalationModelType;
use App\Enums\EscalationType;
use App\Enums\LinkSendStatus;
use App\Enums\NotificationType;
use App\Enums\PaymentType;
use App\Models\Escalation as EscalationModel;
use App\Models\PaymentLink;
use App\Services\NotificationService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;

class DefaultOnPayment extends Escalation
{
    public function send_escalation()
    {
        try {
            //            [$to_users, $to_supervisors, $to_managers] = $this->get_data();
            [$to_users, $to_supervisors] = $this->get_data();

            foreach ($to_users as $payment_link) {

                $claim = $payment_link->payment?->claim;

                if (!$claim) {
                    continue;
                }

                $assignee = $claim->assignees()
                    ->where('is_active', 1)
                    ->latest()
                    ->first();

                if (!$assignee || !$assignee->toUser) {
                    continue;
                }

                $user = $assignee->toUser;
                if (!$this->is_escalation_sent($payment_link, $user)) {
                    $this->create_escalation($payment_link, $user);
                }
            }

            foreach ($to_supervisors as $payment_link) {

                $claim = $payment_link->payment?->claim;

                if (!$claim) {
                    continue;
                }

                $assignee = $claim->assignees()
                    ->where('is_active', 1)
                    ->latest()
                    ->first();

                if (!$assignee || !$assignee->toUser || !$assignee->toUser->supervisor) {
                    continue;
                }

                $supervisor = $assignee->toUser->supervisor;
                if (!$this->is_escalation_sent($payment_link, $supervisor)) {
                    $this->create_escalation($payment_link, $supervisor);
                }
            }

            //            foreach ($to_managers as $payment_link) {
            //                if (isset($payment_link->payment->claim->assignees()
            //                        ->where("is_active", 1)
            //                        ->latest()
            //                        ->first()->toUser?->supervisor->supervisor)) {
            //
            //                    if (!$this->is_escalation_sent($payment_link, $payment_link->payment->claim->assignees()
            //                        ->where("is_active", 1)
            //                        ->latest()
            //                        ->first()->toUser?->supervisor?->supervisor)) {
            //                        $this->create_escalation($payment_link, $payment_link->payment->claim->assignees()
            //                            ->where("is_active", 1)
            //                            ->latest()
            //                            ->first()->toUser?->supervisor?->supervisor);
            //                    }
            //
            //                }
            //
            //            }
        } catch (Exception $exception) {
                Log::error($exception->getMessage());
        }
    }

    public function get_data()
    {
        $to_user = PaymentLink::with('payment.claim.assignees.toUser.supervisor')->whereNotIn('send_status', [LinkSendStatus::PAID->value, LinkSendStatus::CANCELED->value])
            ->whereNot('type', PaymentType::FULL_PAYMENT->value)
            ->where('is_enabled', 1)
            ->where('due_date', '<=', Carbon::today()->subDays(15))
            ->get();

        $to_supervisor = PaymentLink::with('payment.claim.assignees.toUser.supervisor')->whereNotIn('send_status', [LinkSendStatus::PAID->value, LinkSendStatus::CANCELED->value])
            ->whereNot('type', PaymentType::FULL_PAYMENT->value)
            ->where('is_enabled', 1)
            ->where('due_date', '<=', Carbon::today()->subDays(15))
            ->get();

        //        $to_manager = PaymentLink::with('payment.claim.assignees.toUser.supervisor.supervisor')->whereNotIn("send_status", [LinkSendStatus::PAID->value, LinkSendStatus::CANCELED->value])
        //            ->whereNot('type', PaymentType::FULL_PAYMENT->value)
        //            ->where('is_enabled' , 1)
        //            ->where("due_date", "<=", Carbon::today()->subDays(21))
        //            ->get();

        //        var_dump($to_manager);

        return [
            $to_user,
            $to_supervisor,
            //            $to_manager
        ];
    }

    public function is_escalation_sent($payment_link, $user): bool
    {
        return EscalationModel::where('user_id', $user->id)
            ->where('type', EscalationType::DEFAULT_ON_PAYMENT->value)
            ->where('model_type', EscalationModelType::PAYMENT_LINK->value)
            ->where('model_id', $payment_link->id)
            ->count() > 0;
    }

    public function create_escalation($payment_link, $user)
    {

        $notification = NotificationService::create([
            'title_ar' => __('notification.notice_for_default_on_payment', [], 'ar'),
            'title_en' => __('notification.notice_for_default_on_payment', [], 'en'),
            'description_ar' => __('notification.notice_for_default_on_payment_for_claim', [], 'ar') . '( ' . $payment_link->payment->claim?->claim_number . ' )  ',
            'description_en' => __('notification.notice_for_default_on_payment_for_claim', [], 'en') . '( ' . $payment_link->payment->claim?->claim_number . ' )  ',
            'regarding' => NotificationType::GENERAL->value,
             "user_id" => $user->id,
            "to" => json_encode([$user->id]),
            'links' => json_encode([
                [
                    'title_ar' => 'معلومات المطالبة',
                    'title_en' => 'Claim info',
                    'url' => 'claims/'.$payment_link->payment->claim_id.'/info',
                ],
            ]),
        ]);

        //        $notification = NotificationService::create([
        //            'title_ar' => "تم تصعيد مطالبة متعثرة لك.",
        //            "title_en" => "escalation for. default on payment",
        //            'description_ar' => "تم تصعيد مطالبة متعثرة   " . $payment_link->payment->claim?->claim_number . " لك اليآ",
        //            "description_en" => "claim payment is default on payment (" . $payment_link->payment->claim?->claim_number . ")",
        //            "regarding" => NotificationType::GENERAL->value,
        //            "user_id" => $user->id,
        //            "to" => json_encode([$user->id]),
        //            "links" => json_encode([
        //                [
        //                    'label_ar' => 'عرض تفاصيل الطلب',
        //                    'label_en' => 'Open Claim Info',
        //                    "url" => "claims/info/" . $payment_link->payment->claim_id
        //                ]
        //            ])
        //        ]);

        $escalation = EscalationModel::create([
            'notification_id' => $notification->id,
            'user_id' => $user->id,
            'model_type' => EscalationModelType::PAYMENT_LINK->value,
            'model_id' => $payment_link->id,
            'type' => EscalationType::DEFAULT_ON_PAYMENT->value,
        ]);
        // $this->send_escalation_email($escalation, $user);
        //        Mail::to($user->email)->send(new \App\Mail\EscalationMail($escalation));
    }
}
