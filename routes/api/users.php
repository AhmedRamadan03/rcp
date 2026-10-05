<?php

// List users
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\Users\UserCreateController;
use App\Http\Controllers\Api\V1\Users\UserDelegationController;
use App\Http\Controllers\Api\V1\Users\UserEditController;
use App\Http\Controllers\Api\V1\Users\UserIndexController;
use App\Http\Controllers\Api\V1\Users\UserInfoController;
use App\Models\User;

Route::get('/', [UserIndexController::class, 'index'])
    ->name('users.index')
    ->middleware('can:viewAny,' . User::class);
Route::get('/export', [UserIndexController::class, 'openSpoutExportUsers'])
    ->name('users.openSpoutExportUsers')
    ->middleware('can:viewAny,' . User::class);

Route::get('/filter', [UserIndexController::class, 'listUsersInfo'])
    ->name('users.filter')
    ->middleware('can:viewAny,' . User::class);

Route::get('/trashed', [UserIndexController::class, 'trashed'])
    ->name('users.trashed')
    ->middleware('can:viewAny,' . User::class);
Route::get('/trashed/export', [UserIndexController::class, 'openSpoutExportTrashedUsers'])
    ->name('users.trashed.export')
    ->middleware('can:viewAny,' . User::class);

Route::put('/trashed/{id}/restore', [UserInfoController::class, 'restore'])
    ->name('users.restore')
    ->middleware('can:restore,' . User::class);

// Create user
Route::post('/', [UserCreateController::class, 'store'])
    ->name('users.store')
    ->middleware('can:create,' . User::class);


Route::prefix('profile')->group(function () {
    Route::get('/', [ProfileController::class, 'show'])
        ->name('profile.show');

    Route::post('/image', [ProfileController::class, 'storeProfileImage'])
        ->name('profile.image.update');

    Route::delete('/image', [ProfileController::class, 'deleteProfileImage'])
        ->name('profile.image.delete');

    Route::put('/', [ProfileController::class, 'update'])
        ->name('profile.update');

    Route::put('/settings', [ProfileController::class, 'updateProfileSetting'])
        ->name('profile.update.settings');

    Route::get('/get-info', [ProfileController::class, 'getInfo'])
        ->name('profile.getInfo');
});


// User details and update
Route::prefix('{user}')->group(function () {
    // Get user info
    Route::get('/', [UserInfoController::class, 'show'])
        ->name('users.show')
        ->middleware('can:view,App\Models\User,user');

        

    // Edit user
    Route::put('/', [UserEditController::class, 'update'])
        ->name('users.update')
        ->middleware('can:update,App\Models\User,user');


    Route::delete('/', [UserInfoController::class, 'delete'])
        ->name('users.delete')
        ->middleware('can:delete,App\Models\User');

    // User delegations
    Route::prefix('delegations')->group(function () {
        Route::get('/', [UserDelegationController::class, 'listDelegations'])
            ->name('users.delegations.index')
            ->middleware('can:view,' . User::class);

        Route::post('/', [UserDelegationController::class, 'delegate'])
            ->name('users.delegations.store');
    });
});
