<?php

namespace Database\Seeders;

use App\Enums\LinkSendStatus;
use App\Enums\PaymentType;
use App\Models\PaymentLink;
use Illuminate\Database\Seeder;

class PaymentTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        $payment_links = PaymentLink::all();

        foreach ($payment_links as $payment_link) {
            if ($payment_links->where('payment_id', $payment_link->payment_id)->max('order') > 1) {
                $payment_link->type = PaymentType::INSTALLMENT_PAYMENT->value;
            } elseif ($payment_link->send_status == LinkSendStatus::NOT_SENT->value) {
                $payment_link->type = PaymentType::DELAY_PAYMENT->value;
            } elseif ($payment_link->send_status == LinkSendStatus::SENT->value) {

                $payment_link->type = PaymentType::FULL_PAYMENT->value;

                $payment_link->save();
            }
        }
    }
}
