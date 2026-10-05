<?php

namespace App\Services\Claim\StatusActions;

use App\Enums\AdditionalFileType;
use App\Enums\ClaimStatus;
use App\Enums\FileSource;
use App\Enums\FileVisibility;
use App\Enums\UploadFileType;
use App\Models\AdditionalFile;
use App\Models\Claim;
use App\Services\Claim\ClaimActionOptions;
use App\Services\Claim\CollectedOutOfSystemService;
use App\Services\FilesUploader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

class CollectedOutOfSystemStatusAction implements ClaimStatusAction
{
    public function __construct(
        private readonly CollectedOutOfSystemService $service,
        private readonly FilesUploader $filesUploader,
    ) {}

    public static function requiresFile(): bool
    {
        return config('app.client_name')
            !== \App\Enums\Clients::GIG->value;
    }

    public function handle(Claim $claim,array $item,?int $actingUserId,ClaimActionOptions $options,?UploadedFile $statusFile = null): ?string {
        if ($actingUserId === null) {
            throw new RuntimeException(
                'Cannot collect claim: no internal user is associated with this API credential.'
            );
        }

        // Auth::onceUsingId($actingUserId);
        // التحقق من الملف المرفوع لوحده
        if (static::requiresFile() && !$statusFile) {
            throw new \InvalidArgumentException(
                __('claim.file_is_required')
            );
        }

        if ($statusFile) {
            $fileValidator = Validator::make(
                ['file' => $statusFile],
                [
                    'file' => ['file',  'max:20480', 'mimes:'.implode(',', UploadFileType::extensionsWithoutSvg())], // 5MB
                ]
            );

            if ($fileValidator->fails()) {
                throw new \InvalidArgumentException(
                    $fileValidator->errors()->first()
                );
            }
        }

        $additionalFile = null;

        if ($statusFile) {
            $fileTitle = ClaimStatus::getString(
                ClaimStatus::COLLECTED_OUT_OF_SYSTEM->value
            );

            $urlTitle = Str::slug($fileTitle, '_');

            $file = $this->filesUploader->upload($statusFile, [
                'source' => FileSource::ADDITIONAL_FILES,
                'directory' => 'collected/' . now()->year . '/' . $claim->id . '/' . $urlTitle,
                'uploaded_by' => $actingUserId,
            ]);

            $relativePath = $file->local_path ?? $file->cloud_path;

            $additionalFile = AdditionalFile::create([
                'title' => $fileTitle,
                'url' => $relativePath,
                'file_id' => $file->id,
                'claim_id' => $claim->id,
                'note' => $item['note'] ?? null,
                'uploaded_by' => $actingUserId,
                'type' => AdditionalFileType::COLLECTED_OUT_OF_SYSTEM->value,
                'attachment_status' => FileVisibility::UNPUBLISHED->value,
            ]);
        }

        return $this->service->execute(
            claim: $claim,
            data: $item,
            additionalFile: $additionalFile,
            actingUserId: $actingUserId,
            options: $options,
        );
    }
}