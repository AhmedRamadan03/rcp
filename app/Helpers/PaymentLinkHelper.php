<?php

namespace App\Helpers;

use App\Enums\LinkSendStatus;
use App\Enums\PaymentType;
use App\Models\PaymentLink;
use Illuminate\Support\Facades\Log;

class PaymentLinkHelper
{
    public static function createUnique(
        $paymentId,
        $amount,
        $dueDate,
        $type = PaymentType::FULL_PAYMENT->value,
        $sendStatus = LinkSendStatus::NOT_SENT->value,
        $link = null,
        $viewsCount = 0,
        $lastAccess = null,
        $order = 1,
        $isEnabled = 1,
        $verificationCode = null,
        $collectorId = null,
        $attempt = 0
    ){
        // Prevent infinite recursion
        if ($attempt > 10) {
            throw new \RuntimeException('Failed to generate unique payment link after multiple attempts.');
        }

        $link = $link ?? substr(str_shuffle(str_repeat('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', 6)), 0, 6);

        try {
            return PaymentLink::create([
                'payment_id' => $paymentId,
                'send_status' => $sendStatus,
                'amount' => $amount,
                'due_date' => $dueDate,
                'type' => $type,
                'link' => $link,
                'views_count' => $viewsCount,
                'last_access' => $lastAccess,
                'order' => $order,
                'is_enabled' => $isEnabled,
                'verification_code' => $verificationCode,
                'collector_id' => $collectorId,

            ]);

        } catch (\Exception $exception) {
            // PostgreSQL unique violation = 23505
            if ($exception instanceof \PDOException && $exception->getCode() === '23505') {
                Log::warning('Duplicate link generated, retrying...', [
                    'link' => $link,
                    'attempt' => $attempt,
                ]);

                return self::createUnique(
                    paymentId: $paymentId,
                    amount: $amount,
                    dueDate: $dueDate,
                    type: $type,
                    viewsCount: $viewsCount,
                    lastAccess: $lastAccess,
                    order: $order,
                    isEnabled: $isEnabled,
                    verificationCode: $verificationCode,
                    collectorId: $collectorId,
                    attempt: $attempt + 1
                );
            } else {
                Log::error(json_encode($exception->getMessage()));
            }
            // Re-throw other DB errors
            throw $exception;
        }
    }
}
