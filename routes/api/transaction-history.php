<?php

use App\Http\Controllers\Api\V1\TransactionHistoryController;

Route::get('/', [TransactionHistoryController::class, 'index'])
    ->name('transaction-history.index');
    // we will use policy to conditionaly return data see TransactionService
    //->middleware('can:viewAll,App\Models\PaymentTransaction');
    
Route::get('/export', [TransactionHistoryController::class, 'export'])
    ->name('transaction-history.export');
    // same as index
    //->middleware('can:export,App\Models\PaymentTransaction');
Route::post('/{transactionId}/enquiry', [TransactionHistoryController::class, 'enquiry']);
