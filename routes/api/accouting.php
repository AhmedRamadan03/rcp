<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\PermissionController;
use App\Models\Claim;
use App\Models\Permission;


Route::get('/', [AccountController::class, 'index'])
    ->name('accouting.index')
    ->middleware('can:showAccount,' . Permission::class);

Route::get('/export', [AccountController::class, 'exportOpenSpout'])
    ->name('accouting.exportOpenSpout')
    ->middleware('can:showAccount,' . Permission::class);


Route::put('/update-claims', [AccountController::class, 'updateClaims'])
    ->name('accouting.updateClaim')
    ->middleware('can:update_billed,' . Claim::class);

Route::post('/update-claims', [AccountController::class, 'update'])
    ->name('accouting.update')
    ->middleware('can:update_billed,' . Claim::class);

Route::get('/excel-update-claim-template', [AccountController::class, 'downloadUpdateClaimsTemplate'])
    ->name('claims.excelUpdateClaimsTemplate')
    ->middleware('can:update_billed,App\Models\Claim');
