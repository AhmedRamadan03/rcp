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

class PaymentTime extends Escalation
{
    public function send_escalation()
    {
        try {
            //            [$to_supervisors, $to_managers] = $this->get_data();
            [$to_users] = $this->get_data();

            foreach ($to_users as $payment_link) {
                $claim = $payment_link->payment?->claim;

                if (!$claim) {
                    continue;
                }
                $assignee = $claim->assignees()->where('is_active', 1)->latest()->first()?->toUser;

                if (!$assignee) {
                    continue;
                }
                if (!$this->is_escalation_sent($payment_link, $assignee)) {
                    $this->create_escalation($payment_link, $assignee);
                }
                
            }

            //            foreach ($to_managers as $payment_link) {
            //                if (isset($payment_link->payment->claim->assignees()
            //                        ->where("is_active", 1)
            //                        ->latest()
            //                        ->first()->toUser?->supervisor->supervisor)) {
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

        $to_supervisor = PaymentLink::with('payment.claim.assignees.toUser.supervisor')->where('send_status', '<>', LinkSendStatus::PAID->value)
            ->where('send_status', '<>', LinkSendStatus::CANCELED->value)
            ->whereIn('type', [PaymentType::DELAY_PAYMENT->value,  PaymentType::INSTALLMENT_PAYMENT->value])
            ->where('due_date', '<=', Carbon::today()->subDays(2))
            ->where('is_enabled', 1)
            ->get();

        //        $to_manager = PaymentLink::with('payment.claim.assignees.toUser.supervisor.supervisor')->where("send_status", "<>", LinkSendStatus::PAID->value)
        //            ->whereIn("type" , [PaymentType::DELAY_PAYMENT->value,  PaymentType::INSTALLMENT_PAYMENT->value] )
        //            ->where("due_date", "<=", Carbon::today()->subDays(7))
        //            ->where('is_enabled' , 1)
        //            ->get();

        //        var_dump($to_manager);

        return [
            $to_supervisor,
            //            $to_manager
        ];
    }

    public function is_escalation_sent($payment_link, $user): bool
    {
        return EscalationModel::where('user_id', $user->id)
            ->where('type', EscalationType::PAYMENT_TIME->value)
            ->where('model_type', EscalationModelType::PAYMENT_LINK->value)
            ->where('model_id', $payment_link->id)
            ->count() > 0;
    }

    public function create_escalation($payment_link, $user)
    {
        $notification = NotificationService::create([
            'title_ar' => __('notification.notice_for_due_payment', [], 'ar'),
            'title_en' => __('notification.notice_for_due_payment', [], 'en'),
            'description_ar' => __('notification.notice_for_due_Payment_for_claim', [], 'ar') . '( ' . $payment_link->payment->claim?->claim_number . ' )  ',
            'description_en' => __('notification.notice_for_due_Payment_for_claim', [], 'en') . '( ' . $payment_link->payment->claim?->claim_number . ' )  ',
            'regarding' => NotificationType::GENERAL->value,
            'user_id' => $user->id,
            'to' => json_encode([$user->id]),
            'links' => json_encode([
                [
                    'title_ar' => 'الطلبات المفتوحة',
                    'title_en' => 'Open requests',
                    'url' => 'claims/' . $payment_link->payment->claim_id . '/info'
                ],
            ]),
        ]);

        $escalation = EscalationModel::create([
            'notification_id' => $notification->id,
            'user_id' => $user->id,
            'model_type' => EscalationModelType::PAYMENT_LINK->value,
            'model_id' => $payment_link->id,
            'type' => EscalationType::PAYMENT_TIME->value,
        ]);
        // $this->send_escalation_email($escalation, $user);
        //        Mail::to($user->email)->send(new \App\Mail\EscalationMail($escalation));

    }
}
