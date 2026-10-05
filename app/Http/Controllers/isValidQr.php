<?php

namespace App\Http\Controllers;

use App\Models\Discharge;
use App\Models\Voucher;

class isValidQr extends Controller
{
    public function voucher($id)
    {
        try {
            $voucher = Voucher::findOrFail($id);
            $view = $voucher->is_valid ? 'success' : 'failed';

            return view('voucher.'.$view);
        } catch (\Exception $exception) {
            return view('voucher.not-found');
        }
    }

    public function discharge($id)
    {
        try {
            $discharge = Discharge::findOrFail($id);
            $view = $discharge->is_valid ? 'success' : 'failed';

            return view('discharge.'.$view);
        } catch (\Exception $exception) {
            return view('discharge.not-found');
        }
    }
}