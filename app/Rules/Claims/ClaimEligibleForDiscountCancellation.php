<?php

namespace App\Rules\Claims;

use App\Enums\ClaimStatus;
use App\Models\Claim;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ClaimEligibleForDiscountCancellation implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $claim = Claim::find($value);
        
        if (!$claim) {
            $fail('Claim not found.');
            return;
        }

        // Check if claim has an active discount to cancel
        if (!$claim->hasActiveDiscount()) {
            $fail('No active discount found to cancel.');
            return;
        }

        $invalidStatuses = [
            ClaimStatus::REGISTERED->value,
            ClaimStatus::ASSIGNED->value,
            ClaimStatus::REJECTED->value,
        ];

        if (in_array($claim->status, $invalidStatuses)) {
            $fail("Cannot cancel discount on {$claim->status}");
            return;
        }

        if ($claim->status === ClaimStatus::TRANSFER_TO_ELM->value && !$claim->debtor_phone) {
            $fail("Debtor phone is required to cancel a discount when claim status is {$claim->status} ");
            return;
        }
        
        if ($claim->is_collected) {
            $fail('Cannot cancel discount on a collected claim.');
            return;
        }

        if ($claim->is_close == 1) {
            $fail('Cannot cancel discount on a closed claim.');
            return;
        }
    }
}