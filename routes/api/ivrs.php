<?php

use App\Http\Controllers\Api\V1\IvrController;

Route::get('/', [IvrController::class, 'index'])
    ->name('ivr.index');

Route::get('/export', [IvrController::class, 'OpenSpoutexportIvrs'])
    ->name('ivr.export');