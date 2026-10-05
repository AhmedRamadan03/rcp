<?php

namespace App\Services;

use App\Enums\FileSource;
use App\Enums\UploadedClaimsStatus;
use App\Exports\FailedClaim;
use App\Imports\GIG\ClaimImport;
use App\Imports\UploadClaimsImport;
use App\Models\Claim;
use App\Models\UploadedClaimsFile;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Facades\Excel;

class ClaimsUploadUsingFileService
{
    const GIG = 'GIG';

    const GGI = 'GGI';

    const MIC = 'MIC';
    const ART = 'ART';

    const ClaimImport = [
        self::GIG => ClaimImport::class,
        self::GGI => UploadClaimsImport::class,
        self::MIC => ClaimImport::class,
        self::ART => ClaimImport::class,
    ];

    public function upload_file($user, $file)
    {

        try {

            $directory       = 'uploaded-claims-file/' . Carbon::today()->year;
            $originalFile    = (new FilesUploader)->upload($file, [
                'source'      => FileSource::UPLOADED_CLAIMS_FILES,
                'directory'   => $directory,
                'uploaded_by' => $user->id,
            ]);

            $uploaded_claims_file = UploadedClaimsFile::create([
                'user_id'                => $user->id,
                'client_id'              => $user->client_id,
                'uploaded_claims_status' => UploadedClaimsStatus::SCHEDULED,
                'file_url'               => $originalFile->local_path ?? $originalFile->cloud_path,
                'original_file_id'       => $originalFile->id,
                'inserted_date'          => Carbon::now(),
            ]);

            DB::beginTransaction();
            /**
             * @var $excelClass Importable & SkipsFailures
             */
            $excelClass = new (self::ClaimImport[config('app.client_name')])($user, $uploaded_claims_file); // config('app.client_name') == self::GGI ? new UploadClaimsImport($user , $uploaded_claims_file) : new ClaimImport($user , $uploaded_claims_file);

            $excelClass->import($file);
            $failers = $excelClass->failures()->count();
            $failedClaims = [];

            foreach ($excelClass->failures() as $failure) {
                $failedClaims[] = $failure;
            }

            $failedRelative = 'failedClaims/' . $uploaded_claims_file->id . '/failed.xlsx';
            Excel::store(new FailedClaim($failedClaims), 'public/' . $failedRelative, 'local');

            $failedFile = (new FilesUploader)->registerAndLinkFailedFile(
                $uploaded_claims_file,
                $failedRelative,
                $user->id
            );
            $uploaded_claims_file->uploaded_claims_status = UploadedClaimsStatus::INSERTED;
            $uploaded_claims_file->save();

            DB::commit();

            return [
                Claim::where('file_id', $uploaded_claims_file->id)->get(),
                $failers,
                route('download', ['key' => $failedFile->uuid]),
                route('download', ['key' => $originalFile->uuid]),
            ];

        } catch (Exception $e) {

            DB::rollBack();

            throw new Exception('Failed to upload claims file.' . $e->getMessage());

        }

    }
}
