<?php

namespace App\Traits;

use App\Enums\Clients;
use App\Enums\DebtorType;
use Illuminate\Validation\Rule;

/**
 * Same rules as ClaimController::rules(), extracted so they can be
 * reused per-item in bulk contexts (Partner API) where there is no
 * single $request to read from — only a plain array per claim.
 *
 * $data is expected to already use the SAME field names as the
 * website's internal claim payload (claim_number, debtor_name, ...),
 * not any partner-facing alias — Section 13.2 (client-specific value
 * mapping) is where any external->internal field renaming would
 * happen, one layer above this.
 */
trait ClaimValidationRules
{
    public function claimValidationRules(array $data, string $action): array
    {
        $rules = [
            'claim_number' => ['required'],
            'debtor_name' => ['required'],
            'debtor_type' => ['required'],
            'debtor_nid' => ['required'],
            'accident_date' => ['required'],
            'city_id' => ['sometimes', 'exists:cities,id'],
            'type' => ['required'],
            'remark' => ['nullable', 'min:6'],
            'return_reason_id' => ['required_if:debtor_type,' . DebtorType::INSURED->value],
            'mistake_percentage' => ['required'],
            'plate_number' => ['sometimes'],
            'victim_plate_number' => ['sometimes'],
            'car_type' => ['sometimes'],
            'sub_claim_number' => ['nullable'],
            'external_reference' => ['nullable'],
        ];

        if ($action === 'store') {
            $rules['amount'] = ['required', 'numeric'];
            $rules['city_id'] = ['sometimes', 'exists:cities,id'];
        }

        if ($action === 'update') {
            $rules['amount'] = ['sometimes', 'numeric'];
            $rules['city_id'] = ['nullable', 'exists:cities,id'];
        }

        // Optional intimation date validation
        if (!empty($data['intimation_date'] ?? null)) {
            $rules['intimation_date'] = ['date'];
        } else {
            $rules['intimation_date'] = ['sometimes'];
        }

        // Client-specific rules
        if (config('app.client_name') == Clients::GGI->value) {
            $rules['sub_claim_number'] = ['required'];
            $rules['policy_number'] = ['required'];
        } else {
            $rules['accident_number'] = ['required'];
            $rules['policy_number'] = ['required'];
        }

        // ELM-related phone validation
        if (config('app.elm.enabled')) {
            $rules['debtor_phone'] = [
                'nullable',
                Rule::when(!empty($data['debtor_phone'] ?? null), 'min:10'),
            ];
        } else {
            $rules['debtor_phone'] = ['nullable'];
        }

        return $rules;
    }
}