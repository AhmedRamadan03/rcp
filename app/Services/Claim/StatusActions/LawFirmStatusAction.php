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
use App\Services\Claim\LawFirmService;
use App\Services\FilesUploader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

class LawFirmStatusAction implements ClaimStatusAction
{
    public function __construct(
        private readonly LawFirmService $service,
        private readonly FilesUploader $filesUploader,
    ) {}

    public static function requiresFile(): bool
    {
        return config('app.client_name')!== \App\Enums\Clients::GIG->value;
    }

    public function handle(Claim $claim,array $item,?int $actingUserId,ClaimActionOptions $options,?UploadedFile $statusFile = null): ?string {

        if ($actingUserId === null) {
            throw new RuntimeException(
                'Cannot collect claim: no internal user is associated with this API credential.'
            );
        }
        if ($claim->status == ClaimStatus::DONE->value || $claim->status == ClaimStatus::LAW_FIRM->value) {
            throw new \Exception("You Can not Perform This Action because Claim Status is Already {$claim->status}");
        }

        $validator = Validator::make($item, [
            'other_reason' => 'required',
            'selected_legal_user' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            throw new \InvalidArgumentException(
                $validator->errors()->first()
            );
        }

        // التحقق من الملف المرفوع لوحده
        if (static::requiresFile() && !$statusFile) {
            throw new \InvalidArgumentException(
                __('claim.file_is_required')
            );
        }

        if ($statusFile) {
            $fileValidator = Validator::make(
                ['legal_file' => $statusFile],
                [
                    'legal_file' => ['file',  'max:20480', 'mimes:'.implode(',', UploadFileType::extensionsWithoutSvg())], // 5MB
                ]
            );

            if ($fileValidator->fails()) {
                throw new \InvalidArgumentException(
                    $fileValidator->errors()->first()
                );
            }
        }

        $validated = $validator->validated();
        $validated['note'] = $validated['other_reason'];
        $validated['user_id'] = $actingUserId;
        $validated['selected_legal_user'] = $validated['selected_legal_user'] ?? $validated['user_id'];
        $additionalFile = null;

        if ($statusFile) {
            $fileTitle = ClaimStatus::getString(
                ClaimStatus::LAW_FIRM->value
            );

            $urlTitle = Str::slug($fileTitle, '_');

            $file = $this->filesUploader->upload($statusFile, [
                'source' => FileSource::ADDITIONAL_FILES,
                'directory' => 'legal/' . now()->year . '/' . $claim->id . '/' . $urlTitle,
                'uploaded_by' => $actingUserId,
            ]);

            $relativePath = $file->local_path ?? $file->cloud_path;

            $additionalFile = AdditionalFile::create([
                'title' => $fileTitle,
                'url' => $relativePath,
                'file_id' => $file->id,
                'claim_id' => $claim->id,
                'note' => $validated['note'] ?? null,
                'uploaded_by' => $actingUserId,
                'type' => AdditionalFileType::LAW_FIRM->value,
                'attachment_status' => FileVisibility::UNPUBLISHED->value,
            ]);
        }

       return $this->service->execute(
            claim: $claim,
            data: $validated,
            additionalFile: $additionalFile,
            actingUserId: $actingUserId,
            options: $options,
        );
    }
}