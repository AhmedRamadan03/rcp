<?php

use App\Http\Controllers\Api\V1\CityController;

Route::get('/', [CityController::class, 'index'])
    ->name('cities.index');

