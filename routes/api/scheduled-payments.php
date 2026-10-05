<?php

use App\Http\Controllers\Api\V1\ScheduledPaymentController;

Route::get('/', [ScheduledPaymentController::class, 'index'])->name('scheduled-payments.index');
Route::get('/upcoming', [ScheduledPaymentController::class, 'upcomingPayments'])->name('scheduled-payments.upcoming');
Route::post('/{id}/process', [ScheduledPaymentController::class, 'processPayment'])->name('scheduled-payments.process');
