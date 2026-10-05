<?php

namespace App\Jobs;

use App\Enums\DataHistoryEnum;
use App\Enums\ExportType;
use App\Enums\NotificationType;
use App\Models\DataHistory;
use App\Notifications\ExportReadyNotification;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class   MarkExportAsReady implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $file_name;
    public object $user;
    public ExportType $export_type;
    public ?string $historyRef;

    public function __construct(string $file_name, object $user, ExportType $export_type, ?string $historyRef = null)
    {
        $this->file_name = $file_name;
        $this->user = $user;
        $this->export_type = $export_type;
        $this->historyRef = $historyRef;
    }


    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $downloadUrl = route('downloadExport', ['file' => $this->file_name]);

        $user = $this->user;
        $isCancelled = DataHistory::where('ref', $this->historyRef)
            ->where('status', DataHistoryEnum::CANCELLED->value)
            ->exists();

        if (!$isCancelled) {
            $notification = NotificationService::create([
                'title_ar' => __('notification.export_file', [], 'ar'),
                'title_en' => __('notification.export_file', [], 'en'),
                'description_en' => __('notification.export_file_data', [
                    'type' => __('exports.' . $this->export_type->value, [], 'en'),
                ], 'en'),
    
                'description_ar' => __('notification.export_file_data', [
                    'type' => __('exports.' . $this->export_type->value, [], 'ar'),
                ], 'ar'),
                'regarding' => NotificationType::EXPORT->value,
                'user_id' => $user->id,
                'to' => json_encode([$user->id]),
                'links' => json_encode([
                    [
                        'title_ar' => 'تحميل الملف',
                        'title_en' => 'Download file',
                        'url' => $downloadUrl,
                    ],
                ]),
            ]);
    
            if ($this->historyRef) {
                DataHistory::updateDataHistoryStatus($this->historyRef, $notification->id);
            }
        }

    }
}
