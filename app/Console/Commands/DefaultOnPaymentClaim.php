<?php

namespace App\Console\Commands;

use App\BusinessLogic\PaymentReminder\PaymentReminder;
use App\Enums\ClaimStatus;
use App\Enums\LinkSendStatus;
use App\Enums\NotificationType;
use App\Exceptions\NotAuthorizeException;
use App\Models\PaymentLink;
use App\Models\Setting;
use App\Services\ClaimService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DefaultOnPaymentClaim extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:default-on-payment-claim';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        //
        try {
            $links = PaymentLink::with('payment.claim')
                ->whereIn('send_status', [LinkSendStatus::SENT->value, LinkSendStatus::FAILED->value])
                ->whereIn('type', [
                    \App\Enums\PaymentType::INSTALLMENT_PAYMENT->value,
                    \App\Enums\PaymentType::DELAY_PAYMENT->value]) // \App\Enums\PaymentType::INSTALLMENT_PAYMENT->value \App\Enums\PaymentType::DELAY_PAYMENT->value
                ->where('due_date', '<=', Carbon::today()->subDays(15))
                ->whereHas('payment.claim', function ($query) {
                    $query->whereNotIn('status', [
                        ClaimStatus::CLOSED->value,
                        ClaimStatus::REJECTED->value,
                    ]);
                })

                ->get();


            foreach ($links as $payment_link) {

                $claim = $payment_link->payment->claim;
                if (
                    $claim->status != ClaimStatus::DEFAULT_IN_PAYMENT_BY_SYSTEM->value &&
                    $claim->status != ClaimStatus::DEFAULT_IN_PAYMENT->value
                ) {
                    
                    $previous_status = $claim->status;
                    $claim->status = ClaimStatus::DEFAULT_IN_PAYMENT_BY_SYSTEM->value;
                    $claim->save();

                    PaymentReminder::handel(Setting::paymentReminderStatus(ClaimStatus::DEFAULT_IN_PAYMENT_BY_SYSTEM->value), $claim->id);

                    (new ClaimService)->writeToClaimStatusHistory($claim->id, $previous_status, ClaimStatus::DEFAULT_IN_PAYMENT_BY_SYSTEM->value, $claim->user_id);

                    NotificationService::create([
                        'title_ar' => __('notification.notice_for_default_on_payment', [], 'ar'),
                        'title_en' => __('notification.notice_for_default_on_payment', [], 'en'),
                        'description_ar' => __('notification.notice_for_default_on_payment_for_claim', [], 'ar') . '( ' . $claim->claim_number . ' )  ',
                        'description_en' => __('notification.notice_for_default_on_payment_for_claim', [], 'en') . '( ' . $claim->claim_number . ' )  ',
                        'regarding' => NotificationType::GENERAL->value,
                        'user_id' => $claim->assignee?->to,
                        'to' => json_encode([$claim->assignee?->to]),
                        'links' => json_encode([
                            [
                                'title_ar' => ' انشاء فاتورة',
                                'title_en' => ' letter generation ',
                                'url' => 'claims/' . $claim?->id . '/info',
                            ],
                        ]),
                    ]);

                }
            }


        } catch (\Exception $e) {
            throw new NotAuthorizeException($e->getMessage());
        }
    }
}
