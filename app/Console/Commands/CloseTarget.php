<?php

namespace App\Console\Commands;

use App\Enums\NotificationType;
use App\Enums\TargetStatus;
use App\Models\Target;
use App\Models\TargetPeriod;
use App\Models\User;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CloseTarget extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:close-target';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $today = Carbon::today()->format('Y-m-d');

        $peroid = TargetPeriod::where('is_active', true)
            ->where('end_date', '<', $today)
            ->first();

        if (! $peroid) {
            return 0;
        }

        $supervisorIds = [];
        $userIds = [];

        $targets = Target::where('period_id', '<=', $peroid->id)
            ->get();

        foreach ($targets as $target) {
            if ($target->remaining == 0) {
                $target->status = TargetStatus::ACHIEVED->value;
                $target->is_completed = 1;
            } elseif ($target->remaining > 0) {
                $target->status = TargetStatus::MISSED->value;
                $target->is_completed = 0;
            }
            $target->save();
            $userIds[] = $target->user_id;
        }

        $peroid->is_active = 0;
        $peroid->save();

        $supervisorIds = User::whereIn('id', $userIds)->select('supervisor_id')->get()->unique()->pluck('supervisor_id')->toArray();

        foreach ($supervisorIds as $supervisorId) {
            $notification = NotificationService::create([
                'title_ar' => __('notification.target_ends', [], 'ar'),
                'title_en' => __('notification.target_ends', [], 'en'),
                'description_ar' => __('notification.notice_for_target_ends', [], 'ar'),
                'description_en' => __('notification.notice_for_target_ends', [], 'en'),
                'regarding' => NotificationType::GENERAL->value,
                'user_id' => $supervisorId,
                'to' => json_encode([$supervisorId]),
                'links' => json_encode([
                    [
                        'title_ar' => __('notification.targets', [], 'ar'),
                        'title_en' => __('notification.targets', [], 'en'),
                        'url' => 'target-management',
                    ],
                ]),
            ]);
        }

    }
}
