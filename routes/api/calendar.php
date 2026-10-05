<?php

use App\Http\Controllers\Api\V1\CalendarController;

Route::get('/', [CalendarController::class, 'index'])
    ->name('calendar.index');

// Route::put('/reminders/toggle', [CalendarController::class, 'toggleReminderStatus'])
//     ->name('calendar.reminders-toggle');

Route::put('/reminders/{reminderId}/toggle', [CalendarController::class, 'toggleReminderStatus'])
    ->name('calendar.reminder-toggle');
