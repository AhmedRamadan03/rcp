<?php

namespace App\Exports;

use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;

class RejectedClaims implements FromView
{
    public function __construct(
        public $rejectedClaims,
    ) {}

    public function view(): View
    {
        return view('exports.rejected-claims', [
            'rejected_claims' => $this->rejectedClaims,
        ]);

    }
}
