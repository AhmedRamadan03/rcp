<?php

namespace App\BusinessLogic\PaymentReminder;

use App\Models\PaymentLink;

class PaymentReminder
{
    public static function handel($enable_or_disable, $claim_id)
    {
        try {
            ! $enable_or_disable ? static::deactivate($claim_id) : null;
        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }

    }

    public static function activate($claim_id)
    {
        try {
            PaymentLink::whereHas('payment.claim', function ($query) use ($claim_id) {
                $query->where('id', $claim_id);
            })
                ->update(['is_enabled' => 1]);

            return true;
        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }

    }

    public static function deactivate($claim_id)
    {
        try {
            PaymentLink::whereHas('payment.claim', function ($query) use ($claim_id) {
                $query->where('id', $claim_id);
            })
                ->update(['is_enabled' => 0]);

            return true;
        } catch (\Exception $exception) {
            throw new \Exception($exception->getMessage());
        }

    }
}
