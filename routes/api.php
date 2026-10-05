<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\PaymentWebhookController;
use App\Http\Requests\Request;
use App\Models\City;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use App\Http\Controllers\Api\V1\DebtorController;
use App\Http\Controllers\Api\V1\PaymentLinkController;
use App\Http\Controllers\Api\V1\FileController;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/


Broadcast::routes([
    'middleware' => ['auth:sanctum'],
    'prefix' => 'v1',
]);

// Observability: liveness probe for uptime checks, and browser error intake.
Route::get('health', \App\Http\Controllers\Api\HealthController::class)->name('health');
Route::post('v1/client-errors', [\App\Http\Controllers\Api\V1\ClientErrorController::class, 'store'])
    ->middleware('throttle:client-errors')
    ->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class.':api')
    ->name('client-errors.store');

Route::prefix('v1/payments')->group(function () {
    Route::post('/webhook', [PaymentWebhookController::class, 'webhook']);
});


Route::prefix('v1/claim-info')->group(function () {
    Route::post('/{link}/verify', [DebtorController::class, 'verifyCode'])->middleware('throttle:10,1');
    Route::get('/{link}/callback', [DebtorController::class, 'callback'])->name('debtor.callback');
    Route::middleware('client-view')->group(function () {
        Route::get('/get-bank-info', [DebtorController::class, 'getBankInfo']);
        Route::get('/obligation-letter/{link}', [DebtorController::class, 'obligationLetter']);
        Route::post('/logout', [DebtorController::class, 'clientViewLogout']);
        Route::get('/{link}', [DebtorController::class, 'requestCode'])->name('debtor.request-code');

        Route::get('/{link}/fetch-data/{type}', [DebtorController::class, 'fetchData']);
        Route::post('/create-request', [DebtorController::class, 'createRequest']);
        Route::post('/cancel-request/{id}', [DebtorController::class, 'cancelRequest']);
        Route::get('/pay/{link}', [PaymentLinkController::class, 'pay']);
        Route::post('/update-debtor-phone/{id}/send-otp', [DebtorController::class, 'sendUpdatePhoneOtp']);
        Route::post('/update-debtor-phone/{id}/verify-otp', [DebtorController::class, 'verifyUpdatePhoneOtp']);
    });
});
Route::post('v1/redirect-payment', [DebtorController::class, 'redirectPayment']);




Route::get('/v1/show-file/{key?}', [FileController::class, 'serve'])
    ->middleware('authorize.file.access')
    ->name('download');

Route::get('/v1/public-file/{key?}', [FileController::class, 'serve'])
    ->middleware('authorize.file.access')
    ->name('public.file');


Route::get('/v1/download-export/exports/{file?}', function ($file) {
    $path = storage_path('app/public/exports/'.$file);

    if (! File::exists($path)) {
      return response()->json(['status' => false, 'message' => 'File not found']);
    }
    return Storage::disk('public')->download('exports/' . $file);
})->name('downloadExport');

Route::get('cities', function (Request $request) {

    return collect(Cache::remember('cities', null, function () {
        return City::all();
    }))

        ->filter(function ($item) use ($request) {
            if ($request->search) {
                return Str::contains($item['name'], $request->search);
            }

            return true;
        })
        ->take(100)
        ->select(['name', 'id']);
})->name('cities-old.index');

//Route::post('/maqsam/inquiries', [MaqsamController::class, 'createInquiry']);
//Route::post('/maqsam/callback', [MaqsamController::class, 'handleCallback']);
//Route::get('/claims-data', action: [App\Http\Controllers\Api\claims\ClaimsChartData::class, 'index']);




require base_path('routes/api/clientapi.php');

// Prototype routes
Route::post('v1/auth/reset-password', [AuthController::class, 'resetPassword'])->name('auth.reset-password');
Route::post('v1/auth/forget-password', [AuthController::class, 'forgotPassword'])->name('auth.forget-password');
Route::prefix('v1')->middleware(['auth:sanctum'])->group(function () {
    Route::post('auth/change-password-first-time', [AuthController::class, 'changePasswordFirstTime'])->name('auth.change-password-first-time');
});
Route::prefix('v1')->middleware(['auth:sanctum', 'first_time_login'])->group(function () {
    require base_path('routes/api/base.php');
});
    