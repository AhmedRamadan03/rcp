<?php

namespace App\Console\Commands;

use App\Enums\LinkSendStatus;
use App\Models\Payment;
use App\Models\PaymentLink;
use Carbon\Carbon;
use Illuminate\Console\Command;

class FixInstallmentIssue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:fix-installment-issue';

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
        $groups = PaymentLink::with('transactions')
//            ->where('send_status', LinkSendStatus::CANCELED->value)
//            ->where('payment_id', '<', 14)
            ->orderBy('id', 'desc')

            ->get()
            ->groupBy('payment_id');

        foreach ($groups as $group) {
            $payment = Payment::find($group->first()->payment_id);
            $total_amount = $payment->amount;
            $non_cancled_link = [];
            $order = 100;
            foreach ($group as $link) {
                if ($link->order < $order) {
                    $non_cancled_link[] = $link;
                    $order = $link->order;
                    $total_amount -= (int) $link->amount;
                    if ($link->send_status == LinkSendStatus::CANCELED->value) {
                        if ($link->transactions->where('send_status', LinkSendStatus::PAID->value)->count() > 0) {
                            $link->send_status = LinkSendStatus::PAID->value;
                        } elseif (Carbon::parse($link->due_date) < Carbon::tomorrow()) {
                            $link->send_status = LinkSendStatus::SENT->value;
                        } else {
                            $link->send_status = LinkSendStatus::NOT_SENT->value;
                        }
                        $link->save();
                    }
                }
                if ($total_amount == 0) {
                    break;
                }

            }

        }
    }
}
