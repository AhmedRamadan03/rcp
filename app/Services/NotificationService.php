<?php

namespace App\Services;

use App\Events\NotificationEvent;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    public static function create($data = [])
    {
        $notification = Notification::create($data);

        Broadcast(new NotificationEvent($notification))->toOthers();

        return $notification;
    }

    public static function asRead($notis)
    {
        return DB::table('notifications')
            ->whereIn('id', $notis)
            ->update(['viewed' => true]);
    }
}
