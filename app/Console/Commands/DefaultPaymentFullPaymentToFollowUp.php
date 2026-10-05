<?php

namespace App\Console\Commands;

use App\Enums\ClaimStatus;
use App\Enums\LinkSendStatus;
use App\Models\Claim;
use Illuminate\Console\Command;

class DefaultPaymentFullPaymentToFollowUp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:default-payment-full-payment-to-follow-up';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        //
        Claim::where('status', ClaimStatus::DEFAULT_IN_PAYMENT_BY_SYSTEM->value)
            ->whereHas('payments.links', function ($query) {
                $query->where('type', 'full_payment')
                    ->where('send_status', '!=', LinkSendStatus::CANCELED->value);

            })->update([
                'status' => ClaimStatus::FOLLOW_UP->value,
            ]);
    }
}
