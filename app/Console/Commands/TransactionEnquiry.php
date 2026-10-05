<?php

namespace App\Console\Commands;

use App\Actions\TransactionStatusEnquiryAction;
use App\Enums\PaymentTransactionStatus;
use App\Models\PaymentTransaction;
use Illuminate\Console\Command;

class TransactionEnquiry extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:transaction-enquiry';

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

        try {

            $transactions = PaymentTransaction::whereIn('send_status', [
                PaymentTransactionStatus::CREATED->value,
                PaymentTransactionStatus::PENDING->value,
            ])->get();


            foreach ($transactions as $transaction) {
                (new TransactionStatusEnquiryAction($transaction->transaction_ref))->execute();
            }
        } catch (\Throwable $th) {

        }

    }
}
