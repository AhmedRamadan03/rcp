<?php
use App\Services\TechSupport;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\isValidQr;


Route::get('/', function () {
    return view('welcome');
});

Route::prefix('is-valid')->group(function () {
    Route::get('/voucher/{id}', [isValidQr::class, 'voucher']);
    Route::get('/discharge/{id}', [isValidQr::class, 'discharge']);
});

Route::get('/redirect/technical-support-portal', function () {
    $data = TechSupport::loginIfExists(auth()->user());

    return redirect()->away(techSupportUrl().$data['rcp_token']);
})->name('technical-support-portal');

Route::get('/bit.ly/{link}', function ($link) {
    return redirect("/claim-info/{$link}/verify");
});
