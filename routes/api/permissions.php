<?php

use App\Http\Controllers\Api\V1\PermissionController;
use App\Models\Permission;

// unused endpoints
// Route::get('/', [PermissionController::class, 'index'])
//     ->name('permission.index')
//     ->middleware('can:viewAny,' . Permission::class);

// Route::get('/default/{roleId}', [PermissionController::class, 'getDefaultPermissions'])
//     ->name('permission.default')
//     ->middleware('can:viewAny,' . Permission::class);

Route::get('/general-permissions', [PermissionController::class, 'getGeneralPermissions'])
    ->name('permission.getGeneralPermissions')
    ->middleware('can:update,' . Permission::class);
Route::put('/', [PermissionController::class, 'update'])
    ->name('permission.update')
    ->middleware('can:update,' . Permission::class);

