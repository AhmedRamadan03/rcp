<?php

namespace App\Console\Commands;

use App\Enums\DelegationStatus;
use App\Enums\NotificationType;
use App\Models\Delegation;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class DelegationExpiration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:delegation-expiration';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'check delegation expiration every day and change status to expired';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $delegations = Delegation::where('end_date', '<=', now())
            ->where('is_active', 1)
            ->get();

        foreach ($delegations as $delegation) {
            $delegation->update([
                'is_active' => 0,
                'status' => DelegationStatus::CANCELLED_AUTOMATICALLY->value
            ]);

            NotificationService::create([
                'title_ar' => __('notification.delegations', [], 'ar'),
                'title_en' => __('notification.delegations', [], 'en'),
                'description_ar' => __('notification.delegation_claims', [], 'ar'),
                'description_en' => __('notification.delegation_claims', [], 'en'),
                'regarding' => NotificationType::DELEGATIONS->value,
                'user_id' => $delegation->from, // no auth() in commands
                'to' => json_encode([$delegation->from, $delegation->to]),
                'links' => json_encode([
                    [
                        'title_ar' => 'تم انهاء التفويض',
                        'title_en' => 'delegation cancelled',
                        'url' => 'delegations',
                    ],
                ]),
            ]);
        }
    }
}
