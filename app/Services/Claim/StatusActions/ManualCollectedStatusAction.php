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
use App\Services\Claim\ManualCollectionService;
use App\Services\FilesUploader;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;

class ManualCollectedStatusAction implements ClaimStatusAction
{
    public function __construct(
        private readonly ManualCollectionService $service,
        private readonly FilesUploader $filesUploader,
    ) {}

    public static function requiresFile(): bool
    {
        return true;
    }

    public function handle(Claim $claim,array $item,?int $actingUserId,ClaimActionOptions $options,?UploadedFile $statusFile = null): ?string {
       
        if ($actingUserId === null) {
            throw new RuntimeException(
                'Cannot collect claim: no internal user is associated with this API credential.'
            );
        }
        if ($claim->status === ClaimStatus::DONE->value || $claim->status === ClaimStatus::MANUAL_COLLECTED->value) {
            throw new \Exception('You Can not Perform This Action');
        }
        
        $validator = Validator::make($item, [
            'note' => 'required',
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
                ['invoice_file' => $statusFile],
                [
                    'invoice_file' => ['file',  'max:20480', 'mimes:'.implode(',', UploadFileType::extensionsWithoutSvg())], // 5MB
                ]
            );

            if ($fileValidator->fails()) {
                throw new \InvalidArgumentException(
                    $fileValidator->errors()->first()
                );
            }
        }

        $validated = $validator->validated();
      

        $additionalFile = null;

        if ($statusFile) {
            $fileTitle = ClaimStatus::getString(
                ClaimStatus::MANUAL_COLLECTED->value
            );

            $urlTitle = Str::slug($fileTitle, '_');

            $file = $this->filesUploader->upload($statusFile, [
                'source' => FileSource::ADDITIONAL_FILES,
                'directory' => 'manual/' . now()->year . '/' . $claim->id . '/' . $urlTitle,
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
                'type' => AdditionalFileType::CLAIM_MANUAL_COLLECTED->value,
                'attachment_status' => FileVisibility::PUBLISHED->value,
            ]);

            $validated['file_id'] = $additionalFile->file_id;
        }

        $this->service->execute(
            claim: $claim,
            data: $validated,
            additionalFile: $additionalFile,
            actingUserId: $actingUserId,
            options: $options,
        );
        
        return true;
    }
}