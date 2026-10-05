<?php

use App\Http\Controllers\Api\V1\Claims\ClaimInfoController;
use App\Models\Setting;

$apiRoutes = [
    ['prefix' => 'auth',                'file' => 'auth.php',                'middleware' => []],
    ['prefix' => 'users',               'file' => 'users.php',               'middleware' => []],
    ['prefix' => 'claims',              'file' => 'claims.php',              'middleware' => []],
    ['prefix' => 'settings',            'file' => 'settings.php',            'middleware' => []],
    ['prefix' => 'requests',            'file' => 'requests.php',            'middleware' => []],
    ['prefix' => 'targets',             'file' => 'targets.php',             'middleware' => []],
    ['prefix' => 'reports',             'file' => 'reports.php',             'middleware' => []],
    ['prefix' => 'roles',               'file' => 'roles.php',               'middleware' => []],
    ['prefix' => 'calendar',            'file' => 'calendar.php',            'middleware' => []],
    ['prefix' => 'notifications',       'file' => 'notifications.php',       'middleware' => []],
    ['prefix' => 'dashboard',           'file' => 'dashboard.php',           'middleware' => []],
    ['prefix' => 'scheduled-payments',  'file' => 'scheduled-payments.php',  'middleware' => []],
    ['prefix' => 'transaction-history', 'file' => 'transaction-history.php', 'middleware' => []],
    ['prefix' => 'cities',              'file' => 'cities.php',              'middleware' => []],
    ['prefix' => 'permissions',         'file' => 'permissions.php',         'middleware' => []],
    ['prefix' => 'client-requests',         'file' => 'client-requests.php',         'middleware' => []],
    ['prefix' => 'delegations',         'file' => 'delegations.php',         'middleware' => []],
    ['prefix' => 'ivrs',         'file' => 'ivrs.php',         'middleware' => []],
    ['prefix' => 'client',         'file' => 'client.php',         'middleware' => []],
    ['prefix' => 'accouting',         'file' => 'accouting.php',         'middleware' => []],
];

foreach ($apiRoutes as $route) {
    Route::prefix($route['prefix'])->name('api.')
        ->middleware($route['middleware'])
        ->group(function () use ($route) {
            require base_path("routes/api/{$route['file']}");
        });

    Route::get('/close-reasons', [ClaimInfoController::class, 'getCloseReasons'])
        ->name('claims.info.close-reasons')
    ;
}
