<?php

namespace App\Http\Requests\V1\Claims\UpdateRequests;

use App\Enums\Clients;
use App\Enums\DebtorType;
use Illuminate\Foundation\Http\FormRequest;
use Propaganistas\LaravelPhone\PhoneNumber;
use Illuminate\Validation\Rule;
use libphonenumber\NumberParseException;

class UpdateClaimRequest extends FormRequest
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

        // Handle undefined city_id sent from the frontend
        if ($this->city_id === 'undefined') {
            $this->merge(['city_id' => null]);
        }
    }

    public function rules(): array
    {
        $rules = [
            'claim_number' => ['required'],
            'debtor_name' => ['required'],
            'debtor_type' => ['required'],
            'debtor_nid' => ['required'],
            'accident_date' => ['required'],
            'city_id' => ['nullable', 'exists:cities,id'],
            'type' => ['required'],
            'remark' => ['nullable', 'min:6'],
            'return_reason_id' => ['required_if:debtor_type,' . DebtorType::INSURED->value],
            'mistake_percentage' => ['required'],
            'plate_number' => ['sometimes'],
            'victim_plate_number' => ['sometimes'],
            'car_type' => ['sometimes'],
            'sub_claim_number' => ['nullable'],
            'amount' => ['sometimes', 'numeric'],
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


    // في UpdateClaimRequest.php
    public static function normalizePhone(?string $phone): ?string
    {
        if (!$phone) {
            return null;
        }

        $phone = trim($phone);

        // لو الرقم فيه كود دولة أصلاً (+966, +20, +971...) هيتحل صح من غير مساعدة
        try {
            $parsed = new PhoneNumber($phone);
            if ($parsed->isValid()) {
                return $parsed->formatE164();
            }
        } catch (\Exception $e) {
            // تجاهل، هنجرب بالدول تحت
        }

        // لو مفيش كود دولة، جرب الدول المحتملة واحدة واحدة
        $candidateCountries = ['SA']; // زوّد أي دولة تانية محتاجها EG

        foreach ($candidateCountries as $country) {
            try {
                $parsed = new PhoneNumber($phone, $country);
                if ($parsed->isValid()) {
                    return $parsed->formatE164();
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        // مفيش دولة نجحت، رجّع الرقم زي ما هو (زي السلوك الأصلي)
        return $phone;
    }
}