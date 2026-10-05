<?php

use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Middleware\DashboardMiddleware;

Route::get('/assigned-claims', [DashboardController::class, 'assignedClaims'])
    ->middleware(DashboardMiddleware::class);
Route::get('/assigned-claims-report', [DashboardController::class, 'assignedClaimsReport'])
    ->middleware(['can:show_all_executive,App\Models\Dashboard']);

Route::get('/assigned-claims-report/export', [DashboardController::class, 'exportAssignedClaimsReport'])
    ->middleware(['can:show_all_executive,App\Models\Dashboard']);

Route::get('/collected-amount', [DashboardController::class, 'collectedClaimAmount'])
    ->middleware(DashboardMiddleware::class);
Route::get('/monthly-claim-details', [DashboardController::class, 'monthlyClaimDetails'])
    ->middleware(DashboardMiddleware::class);
Route::get('/claim-status', [DashboardController::class, 'claimStatus'])
    ->middleware(DashboardMiddleware::class);
Route::get('/payment-methods', [DashboardController::class, 'paymentMethods'])
    ->middleware(DashboardMiddleware::class);
Route::get('/employee-target', [DashboardController::class, 'employeeTarget'])
    ->middleware(DashboardMiddleware::class);
Route::get('/get-years', [DashboardController::class, 'getYears'])
    ->middleware(DashboardMiddleware::class);
Route::get('/status-report', [DashboardController::class, 'statusReport'])
    ->middleware(['can:show_all_executive,App\Models\Dashboard']);

Route::get('/status-report/export', [DashboardController::class, 'exportStatusReport'])
    ->middleware(['can:show_all_executive,App\Models\Dashboard']);

Route::get('/status-report/export-transactions', [DashboardController::class, 'exportStatusReportTransactions'])
    ->middleware(['can:show_all_executive,App\Models\Dashboard']);