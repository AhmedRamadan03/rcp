<?php

use App\Http\Controllers\Api\V1\TargetManagementController;

Route::get('/', [TargetManagementController::class, 'index'])->name('targets.index');
Route::get('/users-have-target', [TargetManagementController::class, 'usersAssignedToTarget'])->name('targets.users');
Route::post('/', [TargetManagementController::class, 'store'])->name('targets.store');
Route::get('/export', [TargetManagementController::class, 'export'])->name('targets.export');
Route::post('/period', [TargetManagementController::class, 'storePeriod'])->name('targets.period.store');
Route::get('/{targetId}', [TargetManagementController::class, 'show'])->name('targets.show');
Route::put('/{targetId}', [TargetManagementController::class, 'update'])->name('targets.update');
Route::delete('/{target}', [TargetManagementController::class, 'destroy'])->name('targets.destroy');
