<?php

namespace App\Actions;

use App\Enums\LetterGeneration;
use App\Enums\PaymentTransactionStatus;
use App\Exceptions\AutomaticGenerationDoesNotSupport;
use App\Models\PaymentTransaction;
use App\Models\Setting;

class GenerateLetters
{
    public function __construct(
        public PaymentTransaction $paymentTransaction
    ) {}

    public function execute(): void
    {
        try {
            $letters_generation = Setting::where('key', 'claims.letters_generation')->first();
            if(!$letters_generation) {
                return;
            }
            if ($this->paymentTransaction->send_status != PaymentTransactionStatus::PAID->value || $letters_generation->value == LetterGeneration::MANUAL->value) {
                throw new AutomaticGenerationDoesNotSupport;
            }

            $voucherGeneration = new GenerateVoucher($this->paymentTransaction);
            $voucherGeneration->execute();

            $voucherGeneration = new GenerateDischarge($this->paymentTransaction);
            $voucherGeneration->execute();
        } catch (AutomaticGenerationDoesNotSupport $exception) {
            return;
        } catch (\Exception $exception) {
            throw $exception;
        }

    }
}
