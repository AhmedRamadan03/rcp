<?php

use App\Http\Controllers\Api\V1\RequestController;

Route::get('/', [RequestController::class, 'index'])->name('requests.index');
Route::get('/export', [RequestController::class, 'export'])->name('requests.export');

Route::get('/{requestId}', [RequestController::class, 'show'])
    ->name('requests.show')
    ->middleware('can:view,App\Models\StatusChangeRequest,requestId');

Route::post('/{requestId}/reject', [RequestController::class, 'reject'])
    ->name('requests.reject')
    ->middleware('can:reject,App\Models\StatusChangeRequest,requestId');

Route::post('/{requestId}/cancel', [RequestController::class, 'cancel'])
    ->name('requests.cancel')
    ->middleware('can:cancel,App\Models\StatusChangeRequest,requestId');

Route::post('/{requestId}/accept', [RequestController::class, 'accept'])
    ->name('requests.accept')
    ->middleware('can:accept,App\Models\StatusChangeRequest,requestId');
    
Route::post('/{requestId}/escalate', [RequestController::class, 'escalate'])->name('requests.escalate');
