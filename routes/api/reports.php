<?php

// Default on payment claims
use App\Http\Controllers\Api\V1\FinancialReportController;
use App\Http\Controllers\Api\V1\ReportsController;

Route::get('/default-payment', [ReportsController::class, 'defaultOnPayment'])
    ->name('reports.default-payment');
Route::get('/default-payment/export', [ReportsController::class, 'exportDefaultOnPayment'])
    ->name('reports.default-payment.export');

// Rejected claims
Route::get('/rejected', [ReportsController::class, 'rejectedClaims'])
    ->name('reports.rejected');
Route::get('/rejected/export', [ReportsController::class, 'exportRejectedClaims'])
    ->name('reports.rejected.export');
Route::post('/rejected/{rejectedClaimId}/accept', [ReportsController::class, 'acceptRejection'])
    ->name('reports.rejected.accept');
Route::post('/rejected/{rejectedClaimId}/reject', [ReportsController::class, 'rejectRejection'])
    ->name('reports.rejected.reject');

// Targets
Route::get('/targets', [ReportsController::class, 'targets'])
    ->name('reports.targets');

Route::get('/targets/export', [ReportsController::class, 'exportTargets'])
    ->name('reports.targets.export');

Route::get('/targets/{user_id}', [ReportsController::class, 'target'])
    ->name('reports.target')
    ->middleware('can:viewUserTargets,App\Models\Target,user_id');


Route::get('/targets/{user_id}/export', [ReportsController::class, 'exportCollectorTargets'])
    ->name('reports.target.export')
    ->middleware('can:viewUserTargets,App\Models\Target,user_id');

Route::get('/target/data/{target_id}', [ReportsController::class, 'targetData'])
    ->name('reports.targetData')
    ->middleware('can:viewUserTargetData,App\Models\Target,target_id');

Route::get('/target/data/{target_id}/export', [ReportsController::class, 'exportTargetData'])
    ->name('reports.targetDataExport')
    ->middleware('can:viewUserTargetData,App\Models\Target,target_id');



 
// authentication logs
Route::get('/authentication-logs', [ReportsController::class, 'authenticationLog'])
    ->name('reports.authentication-logs');

Route::get('/authentication-logs/export', [ReportsController::class, 'exportAuthenticationLog'])
    ->name('reports.authentication-logs.export');

Route::get('/get-messages', [ReportsController::class, 'getMessages'])
    ->name('reports.getMessages');
Route::get('/get-messages/export', [ReportsController::class, 'OpenSpoutexportMessages'])
    ->name('reports.exportMessages');

Route::get('/claims-assigned-history', [ReportsController::class, 'cliamsAssignedHistory'])
    ->name('reports.cliamsAssignedHistory');
Route::get('/claims-assigned-history/export', [ReportsController::class, 'OpenSpoutExportCliamsAssignedHistory'])
    ->name('reports.exportCliamsAssignedHistory');

Route::get('/data-history', [ReportsController::class, 'getDataHistory'])
    ->name('reports.data-history');
Route::post('/data-history/cancelled/{id}', [ReportsController::class, 'cancellProcessing'])
    ->name('reports.data-history.cancellProcessing');
Route::get('/data-history/export', [ReportsController::class, 'OpenSpoutExportDataHistory'])
    ->name('reports.data-history.export');