<?php

namespace App\Rules\Claims;

use App\Enums\ClaimStatus;
use App\Models\Claim;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ClaimEligibleForDiscount implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $claim = Claim::find($value);
        if (!$claim) {
            $fail('Claim not found.');
            return;
        }

        $invalidStatuses = [
            ClaimStatus::REGISTERED->value,
            ClaimStatus::ASSIGNED->value,
            ClaimStatus::REJECTED->value,
        ];
        if (in_array($claim->status, $invalidStatuses)) {
            $fail("you can't add a discount when the claim status is {$claim->status}.");
            return;
        }

        if ($claim->status === ClaimStatus::TRANSFER_TO_ELM->value && !$claim->debtor_phone) {
            $fail("Debtor phone is required to add a discount when claim status is {$claim->status} ");
            return;
        }

        if ($claim->is_collected) {
            $fail('Claim is already collected.');
            return;
        }

        if ($claim->is_close == 1) {
            $fail('Claim is closed.');
            return;
        }

        if ($claim->hasActiveDiscount()) {
            $fail('Claim already has an active discount.');
            return;
        }

    }
}
