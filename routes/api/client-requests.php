<?php
use App\Http\Controllers\Api\V1\RejectedClaimController;



Route::get('/', [RejectedClaimController::class, 'index'])
    ->name('client.index') ;

Route::get('/export', [RejectedClaimController::class, 'export'])
    ->name('client.export') ;

Route::get('/{rejectedClaim}', [RejectedClaimController::class, 'show'])
    ->name('client.show') ;

Route::post('/{rejectedClaim}/accept', [RejectedClaimController::class, 'accept'])
    ->name('client.accept') ;

Route::post('/{rejectedClaim}/reject', [RejectedClaimController::class, 'reject'])
    ->name('client.reject') ;
