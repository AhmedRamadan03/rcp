<?php


use App\Http\Controllers\Api\V1\DelegationController;



Route::get('/', [DelegationController::class, 'index'])
    ->name('delegations.index')
    ->middleware('can:viewAny,App\Models\Delegation');

Route::get('/export', [DelegationController::class, 'export'])
    ->name('delegations.export')
    ->middleware('can:viewAny,App\Models\Delegation');

Route::post('/', [DelegationController::class, 'store'])
    ->name('delegations.store')
    ->middleware('can:create,App\Models\Delegation');

Route::post('/{delegation}', [DelegationController::class, 'cancel'])
    ->name('delegations.cancel')
    ->middleware('can:update,delegation');
