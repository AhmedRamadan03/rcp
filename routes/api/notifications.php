<?php

use App\Http\Controllers\Api\V1\NotificationController;

Route::get('/', [NotificationController::class, 'index'])
    ->name('notifications.index');

Route::put('/toggle', [NotificationController::class, 'toggleNotificationStatus'])
    ->name('notifications-toggle');

Route::put('/{reminderId}/toggle', [NotificationController::class, 'toggleNotificationStatus'])
    ->name('notification-toggle');

Route::delete('/{reminderId}/delete', [NotificationController::class, 'deleteNotification'])
    ->name('deleteNotification');
