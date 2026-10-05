<?php

use App\Http\Controllers\Api\V1\AuthenticationLogController;
use App\Http\Controllers\Api\V1\TechSupportController;

Route::get('authentication-logs', [AuthenticationLogController::class, 'index']);

// Tech support
Route::prefix('tech-support')->group(function () {
    Route::get('/', [TechSupportController::class, 'index'])->name('tech-support.index');
});
