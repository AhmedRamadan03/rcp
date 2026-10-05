<?php

namespace App\Services\Claim;

use App\Actions\GenerateLetters;
use App\Enums\ClaimStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\LinkSendStatus;
use App\Enums\RequestStatus;
use App\Enums\TransactionType;
use App\Helpers\PaymentLinkHelper;
use App\Models\AdditionalFile;
use App\Models\Claim;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\Setting;
use App\Models\StatusChangeRequest;
use App\Services\ClaimService;
use App\Services\ElmService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TransferToElmService
{
    public function __construct(
        private readonly ClaimService $claimService,
    ) {
    }

    public function execute(
        Claim $claim,
        array $data,
        ?AdditionalFile $additionalFile,
        int $actingUserId,
        ?ClaimActionOptions $options = null
    ): void {
        $options ??= ClaimActionOptions::systemApi();

        DB::transaction(function () use ($claim, $data, $actingUserId) {
            $paymentLink = $claim->payments->first()->links->first();

            $length = strlen($paymentLink->link);
            if ($length > 6) {
                $paymentLink->send_status = LinkSendStatus::CANCELED->value;
                $paymentLink->save();

                $newPaymentLink = PaymentLinkHelper::createUnique(
                    paymentId: $paymentLink->payment_id,
                    amount: $paymentLink->amount,
                    dueDate: $paymentLink->due_date,
                    type: $paymentLink->type,
                    link: $paymentLink->link,
                    viewsCount: $paymentLink->views_count,
                    lastAccess: $paymentLink->last_access,
                    order: $paymentLink->order,
                    isEnabled: $paymentLink->is_enabled,
                    verificationCode: $paymentLink->verification_code,
                    collectorId: $paymentLink->collector_id,
                    attempt: $paymentLink->attempt,
                );

                $paymentLink = $newPaymentLink;
            }
             $elmService = new ElmService($paymentLink);

            // نص الرسالة اللي هيتحفظ في الداتابيز
            $message = $elmService->buildMessageFromTemplate();
            
            if (!$message) {
                // التمبلت المفعّل حاليًا عند علم مش TMPL-03 ولا TMPL-04
               throw new RuntimeException('Unsupported Elm template');
            }

            $previousStatus = $claim->status;

            $elmService->sendElmMessage($message);

            // Update payment link send status after successful ELM request
            $paymentLink->send_status = LinkSendStatus::SENT->value;
            $paymentLink->save();

            $claim->status = ClaimStatus::TRANSFER_TO_ELM->value;
            
            $this->claimService->writeToClaimStatusHistory(
                $claim->id,
                $previousStatus,
                ClaimStatus::TRANSFER_TO_ELM->value,
                $actingUserId,
                $data
            );
            $claim->save();

           
        });
    }
}