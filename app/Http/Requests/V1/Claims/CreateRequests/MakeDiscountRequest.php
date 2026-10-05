<?php

namespace App\Http\Requests\V1\Claims\CreateRequests;

use App\Models\Claim;
use App\Rules\Claims\ClaimEligibleForDiscount;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Contracts\Validation\Validator;

class MakeDiscountRequest extends FormRequest
{
    // Holds the Claim model resolved from the route parameter.
    private $claim;

    /**
     * Number of decimal places used for all money/percentage calculations.
     * Using a fixed scale with BCMath avoids floating point precision issues
     * (e.g. 0.1 + 0.2 !== 0.3 in native float arithmetic).
     */
    private const SCALE = 2;

    /**
     * Temporary storage for any errors that occur during the calculations
     * inside prepareForValidation().
     *
     * Why: $this->validator is NOT available yet inside prepareForValidation()
     * (Laravel builds the validator instance AFTER this method runs), so we
     * can't call $this->validator->errors()->add() directly there without
     * causing a "Call to a member function errors() on null" error.
     * Instead we stash the errors here and flush them later inside
     * withValidator(), where the $validator instance is guaranteed to exist.
     */
    private array $preparationErrors = [];

    /**
     * Whether the current user is allowed to make this request.
     * Returning true here means authorization is either handled elsewhere
     * (middleware/policy) or not required for this endpoint.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Runs automatically before validation rules are applied.
     * Used here to:
     * 1) Load the Claim and calculate the base amount available for discount.
     * 2) Depending on which "type" of input the frontend sent
     *    (percentage / deducted_amount / amount_after_deduction),
     *    calculate the other two values from that single source of truth.
     * 3) Merge the calculated values into the request so they can be used
     *    by rules() and withValidator().
     *
     * All arithmetic here uses BCMath (bcadd/bcsub/bcmul/bcdiv) on strings
     * instead of native float math, to avoid rounding/precision errors when
     * dealing with money.
     */
    protected function prepareForValidation()
    {
        // Get the claim ID from the route and try to load the Claim model.
        $claimId = $this->route('claimId');
        $this->claim = Claim::find($claimId);

        // If the claim doesn't exist, record the error and stop here
        // (no point continuing calculations on a non-existent claim).
        if (!$this->claim) {
            $this->preparationErrors['claim_id'] = 'Claim not found.';
            return;
        }

        // Calculate the amount that's still available to be discounted
        // (total amount - already paid amount). Returned as a decimal string.
        $baseAmount = $this->calculateBaseAmount($this->claim);

        // If there's no payment at all, or the remaining amount is zero/negative,
        // there's nothing left to discount.
        if ($baseAmount === null || bccomp($baseAmount, '0', self::SCALE) <= 0) {
            $this->preparationErrors['base_amount'] = 'No remaining amount available for discount.';
            return;
        }

        // The "type" field tells us which value the frontend is sending as
        // the source of truth: percentage, deducted_amount, or amount_after_deduction.
        $type = $this->type;

        switch ($type) {
            // Case 1: the frontend sent the discount percentage.
            // We calculate the deducted amount and the amount after deduction from it.
            case 'percentage':
                $percentage = $this->toDecimalString($this->percentage);
                // deductedAmount = floor(baseAmount * percentage / 100)
                // bcdiv() truncates (floors) to the given scale by default, same
                // intent as the old floor() call but done in decimal-safe math.
                $rawDeducted = bcdiv(bcmul($baseAmount, $percentage, self::SCALE + 2), '100', self::SCALE + 2);
                $deductedAmount = bcadd($rawDeducted, '0', self::SCALE); // truncate to SCALE
                $amountAfterDeduction = bcsub($baseAmount, $deductedAmount, self::SCALE);
                break;

            // Case 2: the frontend sent the deducted amount directly.
            // We derive the amount after deduction and back-calculate the percentage
            // (mainly for display/record purposes and for the "note required" check below).
            case 'deducted_amount':
                $deductedAmount = $this->toDecimalString($this->deducted_amount);
                $amountAfterDeduction = bcsub($baseAmount, $deductedAmount, self::SCALE);
                $percentage = bccomp($baseAmount, '0', self::SCALE) > 0
                    ? bcdiv(bcmul($deductedAmount, '100', self::SCALE + 2), $baseAmount, self::SCALE)
                    : '0';
                break;

            // Case 3: the frontend sent the amount remaining after the deduction.
            // We derive the deducted amount and back-calculate the percentage.
            case 'amount_after_deduction':
                $amountAfterDeduction = $this->toDecimalString($this->amount_after_deduction);
                $deductedAmount = bcsub($baseAmount, $amountAfterDeduction, self::SCALE);
                $percentage = bccomp($baseAmount, '0', self::SCALE) > 0
                    ? bcdiv(bcmul($deductedAmount, '100', self::SCALE + 2), $baseAmount, self::SCALE)
                    : '0';
                break;

            // Any other/unknown value for "type" is invalid.
            default:
                $this->preparationErrors['type'] = 'Invalid discount type.';
                return;
        }

        // Sanity check: none of the calculated values should end up negative
        // (e.g. deducted_amount > base_amount would make amount_after_deduction negative).
        if (bccomp($deductedAmount, '0', self::SCALE) < 0 || bccomp($amountAfterDeduction, '0', self::SCALE) < 0) {
            $this->preparationErrors['calculation'] = 'Invalid discount calculation resulting in negative values.';
            return;
        }

        // Merge the freshly calculated values into the request.
        // These are now the values that will actually be validated and saved,
        // all derived from a single source (base_amount + type), so they're
        // guaranteed to be internally consistent. Stored as decimal strings.
        $this->merge([
            'base_amount'            => $baseAmount,
            'percentage'             => $percentage,
            'deducted_amount'        => $deductedAmount,
            'amount_after_deduction' => $amountAfterDeduction,
            'claim_id'               => $claimId,
        ]);
    }

    /**
     * Calculates the remaining amount on the claim's first payment
     * that is still available to apply a discount to.
     * Returns a decimal string (or null if there's no payment).
     */
    protected function calculateBaseAmount(Claim $claim)
    {
        $payment = $claim->payments()->first();

        // No payment record at all -> nothing to calculate.
        if (!$payment) {
            return null;
        }

        $amount = $this->toDecimalString($payment->amount);
        $paidAmount = $this->toDecimalString($payment->paid_amount);

        $baseAmount = bcsub($amount, $paidAmount, self::SCALE);

        // Guard against a negative base amount (e.g. overpaid claim).
        if (bccomp($baseAmount, '0', self::SCALE) < 0) {
            return '0';
        }

        return $baseAmount;
    }

    /**
     * Normalizes any numeric input (int, float, numeric string, or null)
     * into a clean decimal string safe to use with BCMath functions.
     * BCMath requires string operands; feeding it raw floats can reintroduce
     * the exact precision issues we're trying to avoid.
     */
    private function toDecimalString($value): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        // number_format with no thousands separator gives us a clean,
        // fixed-precision decimal string regardless of the input type.
        return number_format((float) $value, self::SCALE + 2, '.', '');
    }

    /**
     * Standard Laravel validation rules, applied to the (already recalculated)
     * request data merged in prepareForValidation().
     */
    public function rules(): array
    {
        return [
            'claim_id' => ['required', new ClaimEligibleForDiscount],
            'amount_after_deduction' => 'required|numeric|min:0',
            'percentage' => 'required|numeric|min:0|max:100',
            'deducted_amount' => 'required|numeric|min:0',
            // A note is mandatory only if the requested percentage exceeds
            // what the current user is allowed to approve on their own.
            // Comparison done via bccomp() to stay consistent with decimal math.
            'note' => [
                Rule::requiredIf(fn() => bccomp(
                    $this->toDecimalString($this->percentage),
                    $this->toDecimalString(auth()->user()->effective_discount_percentage),
                    self::SCALE
                ) > 0),
                'min:6'
            ],
            // Restrict "type" to one of the three supported input modes.
            'type' => ['required', Rule::in(['percentage', 'deducted_amount', 'amount_after_deduction'])],
        ];
    }

    /**
     * Adds extra validation logic that runs AFTER the standard rules() pass.
     * This is where $validator is actually available, so this is the correct
     * place to flush any errors collected earlier and to run cross-field checks.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {

            // Flush any errors that were recorded during prepareForValidation()
            // (claim not found, invalid type, negative values, etc.).
            foreach ($this->preparationErrors as $field => $message) {
                $validator->errors()->add($field, $message);
            }

            // If there are already errors (from rules() or preparationErrors),
            // skip the extra consistency check below — no point checking math
            // on data we already know is invalid/incomplete.
            if ($validator->errors()->any()) {
                return;
            }

            $baseAmount = $this->toDecimalString($this->base_amount);
            $deductedAmount = $this->toDecimalString($this->deducted_amount);
            $amountAfterDeduction = $this->toDecimalString($this->amount_after_deduction);

            // Allow a small rounding tolerance (e.g. from truncation in the
            // percentage-based calculation above).
            $tolerance = '1';

            $sum = bcadd($deductedAmount, $amountAfterDeduction, self::SCALE);
            $diff = bcsub($sum, $baseAmount, self::SCALE);
            // abs() equivalent for a decimal string via bccomp against 0.
            $absDiff = bccomp($diff, '0', self::SCALE) < 0 ? bcmul($diff, '-1', self::SCALE) : $diff;

            // Final safety net: deducted_amount + amount_after_deduction should
            // always equal base_amount. Since all three values are now derived
            // from the same calculation in prepareForValidation(), this should
            // basically never fail — but it's kept as a defensive check.
            if (bccomp($absDiff, $tolerance, self::SCALE) > 0) {
                $validator->errors()->add(
                    'calculation',
                    "The sum of deducted amount ({$deductedAmount}) and amount after deduction ({$amountAfterDeduction}) should equal the base amount ({$baseAmount})"
                );
            }
        });
    }

    /**
     * Custom error messages for the standard validation rules.
     */
    public function messages(): array
    {
        return [
            'note.required' => 'A note is required when discount percentage exceeds your authorization limit.',
            'amount_after_deduction.required' => 'Amount after deduction is required.',
            'percentage.required' => 'Discount percentage is required.',
            'deducted_amount.required' => 'Deducted amount is required.',
        ];
    }

    /**
     * Overrides the default failed-validation behavior to return a
     * consistent JSON error response instead of a redirect (this is an API).
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation errors',
            'data' => null,
            'errors' => $validator->errors()
        ], 422));
    }
}