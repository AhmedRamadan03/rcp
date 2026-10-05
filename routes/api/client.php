<?php


use App\Http\Controllers\Api\V1\ClientController;

Route::get('/client-info', [ClientController::class, 'clientInfo'])
    ->withoutMiddleware('auth:sanctum')
    ->name('client.info');

