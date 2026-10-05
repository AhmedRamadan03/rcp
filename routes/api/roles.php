<?php

use App\Http\Controllers\Api\V1\RoleController;
use App\Models\Role;

Route::get('/', [RoleController::class, 'index'])
    ->name('roles.index')
    ->middleware('can:viewAny,' . Role::class);
    
Route::get('/export', [RoleController::class, 'openSpoutExportRoles'])
    ->name('roles.openSpoutExportRoles')
    ->middleware('can:viewAny,' . Role::class);

Route::get('/all', [RoleController::class, 'getAllRoles'])
    ->name('roles.getAllRoles')
    ->middleware('can:viewAny,' . Role::class);

Route::get('/{id}', [RoleController::class, 'show'])
    ->name('roles.show')
    ->middleware('can:view,' . Role::class);

Route::post('/', [RoleController::class, 'store'])
    ->name('roles.store')
    ->middleware('can:create,' . Role::class);

Route::put('/{id}', [RoleController::class, 'update'])
    ->name('roles.update')
    ->middleware('can:update,' . Role::class);

Route::delete('/{id}', [RoleController::class, 'destroy'])
    ->name('roles.destroy')
    ->middleware('can:delete,' . Role::class);
