<?php

namespace App\Http\Requests\V1\Claims\CreateRequests;

use App\Enums\Clients;
use App\Enums\DebtorType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Propaganistas\LaravelPhone\PhoneNumber;


class StoreClaimRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $debtorPhone = $this->debtor_phone ? trim((string) $this->debtor_phone) : null;

        if ($debtorPhone) {
            try {
                $parsed = new PhoneNumber($debtorPhone);
                if ($parsed->isValid()) {
                    $debtorPhone = $parsed->formatE164();
                }
            } catch (\Propaganistas\LaravelPhone\Exceptions\NumberParseException) {
                // Leave as-is; the `phone` rule below will reject it with a proper message.
            }
        }
        $this->merge([
            'claim_number' => $this->claim_number ? trim((string) $this->claim_number) : $this->claim_number,
            'debtor_phone' => $debtorPhone,
            'debtor_nid' => $this->debtor_nid ? trim((string) $this->debtor_nid) : $this->debtor_nid,
            'sub_claim_number' => $this->sub_claim_number ? trim((string) $this->sub_claim_number) : null,
        ]);
    }

    public function rules(): array
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
            'amount' => ['required', 'numeric'],
            'debtor_phone' => [
                'nullable',
                Rule::when($this->filled('debtor_phone'), 'phone:INTERNATIONAL'),
            ],
            'external_reference' => ['nullable', 'string'],
        ];

        $rules['intimation_date'] = !empty($this->intimation_date)
            ? ['date', 'after_or_equal:accident_date']
            : ['sometimes'];

        if (config('app.client_name') == Clients::GGI->value) {
            $rules['sub_claim_number'] = ['required'];
            $rules['policy_number'] = ['required'];
        } else {
            $rules['accident_number'] = ['required'];
            $rules['policy_number'] = ['sometimes'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'debtor_phone.phone' => 'Please enter a valid phone number including the country code',
            'intimation_date.after_or_equal' => 'The intimation date must be a date after or equal to the accident date.',
        ];
    }
}
