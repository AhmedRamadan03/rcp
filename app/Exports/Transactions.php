<?php

namespace App\Exports;

use App\Models\PaymentTransaction;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Maatwebsite\Excel\Concerns\FromView;

class Transactions implements FromView
{
    /**
     * @param  Collection<PaymentTransaction>  $transactions
     */
    public function __construct(public Collection $transactions) {}

    public function view(): View
    {
        $data = [];
        foreach ($this->transactions as $transaction) {
            $data[] = $transaction->renderTransaction();
        }

        return view('exports.transactions', [
            'transactions' => $data,
        ]);
    }
}
