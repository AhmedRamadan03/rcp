<?php

use App\Http\Controllers\Api\V1\AuthController;

Route::post('/login', [AuthController::class, 'login'])->withoutMiddleware('auth:sanctum')->name('auth.login');

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');
    Route::get('/me', [AuthController::class, 'me'])->name('auth.me');
});
