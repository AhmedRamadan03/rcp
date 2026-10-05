<?php

namespace App\Services;

use App\Enums\ClaimStatus;
use App\Enums\FileSource;
use App\Enums\UploadedClaimsStatus;
use App\Exports\FailedBulkAssignExport;
use App\Exceptions\NotExistsException;
use App\Models\Assigning;
use App\Models\Claim;
use App\Models\User;
use App\Models\UploadedClaimsFile;
use App\Imports\BulkAssignClaimsImport;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class AssigningService
{
    public function create($assigning)
    {

        // TODO move the authorization to the policy the commented code below
        // if ( !Claim::where('id' ,$assigning['claim_id'])
        //         ->where('client_id' , 1) //TODO Auth User Client id
        // ){
        //     throw new NotExistsException('Claim does not exists');
        // }

        // if (!User::where($assigning['supervisor_id']) ){
        //     throw new NotExistsException('Supervisor does not exists');
        // }

        // if ( !User::find($assigning['assign_to']) ){
        //     throw new NotExistsException('Assignee does not exists');
        // }

        // if ( !User::find($assigning['assign_to']) ){
        //     throw new NotExistsException('Assignee does not exists');
        // }

        try {

            $assignings = Assigning::whereIn('claim_id', $assigning['claims_id'])
                ->latest('id')
                // ->unique('claim_id')
                ->get();

            // TODO seprate the calculation into out function
            $employees_to = $assigning['to'];
            $claims = collect($assigning['claims_id']);

            $claim_ratio_to_employees = round(count($claims) / count($employees_to));
            $employees_claim = [];
            foreach ($employees_to as $index => $employee_id) {
                $employees_claim += array_fill_keys($claims->slice(($claim_ratio_to_employees * $index), $claim_ratio_to_employees)->all(), $employee_id);
            }

            // return $employees_claim;
            $assignings_data = [];

            foreach ($assigning['claims_id'] as $claim_id) {
                $lastAssign = $assignings->where('claim_id', $claim_id)->first();

                $assignings_row['claim_id'] = $claim_id;
                Claim::find($claim_id)
                    ->assignees()->update(
                        [
                            'is_active' => 0,
                        ]
                    );
                $assignings_row['supervisor_id'] = $assigning['supervisor_id'] ?? null;
                $assignings_row['to'] = $employees_claim[$claim_id];
                $assignings_row['from'] = $lastAssign->to ?? null;
                $assignings_row['assign_date'] = $assigning['assign_date'] ?? now();
                $assignings_row['created_at'] = now();
                $assignings_data[] = $assignings_row;
            }

            Assigning::insert($assignings_data);

            //             return Claim::whereIn('id' , $assigning["claims_id"])->update([
            //                'status' => ClaimStatus::ASSIGNED->value
            //             ]);

        } catch (Exception $e) {
            throw new NotExistsException($e->getMessage());
            // throw new NotExistsException('Claim does not exists');
        }
    }

    public function bulkCreate($assigning)
    {
        try {

            // Get previous assignments for all claims
            $assignings = Assigning::whereIn('claim_id', $assigning['claims_id'])
                ->latest('id')
                ->get();

            $assignings_data = [];

            foreach ($assigning['claims_id'] as $claim_id) {
                // Get last assignment for this specific claim
                $lastAssign = $assignings->where('claim_id', $claim_id)->first();

                // Deactivate all current assignees for this claim
                Claim::find($claim_id)
                    ->assignees()
                    ->update(['is_active' => 0]);

                // Prepare new assignment record
                $assignings_row['claim_id'] = $claim_id;
                $assignings_row['supervisor_id'] = $assigning['supervisor_id'] ?? null;
                $assignings_row['to'] = $assigning['to'];
                $assignings_row['from'] = $lastAssign->to ?? null;
                $assignings_row['assign_date'] = now();
                $assignings_row['created_at'] = now();
                $assignings_data[] = $assignings_row;
            }

            Assigning::insert($assignings_data);


        } catch (Exception $e) {
            throw new NotExistsException($e->getMessage());
        }
    }

    public function excelBulkAssign($file)
    {
        DB::beginTransaction();

        try {
            // Upload file
            $directory    = 'uploaded-claims-file/' . Carbon::today()->year;
            $originalFile = (new FilesUploader)->upload($file, [
                'source'      => FileSource::UPLOADED_CLAIMS_FILES,
                'directory'   => $directory,
                'uploaded_by' => auth()->user()->id,
            ]);

            // Create file record
            $uploadedClaimBulkAssignFile = UploadedClaimsFile::create([
                'user_id'                => auth()->user()->id,
                'client_id'              => auth()->user()->client_id,
                'uploaded_claims_status' => UploadedClaimsStatus::SCHEDULED,
                'file_url'               => $originalFile->local_path ?? $originalFile->cloud_path,
                'original_file_id'       => $originalFile->id,
                'inserted_date'          => Carbon::now(),
            ]);

            $excelImporter = new BulkAssignClaimsImport($uploadedClaimBulkAssignFile);
            $excelImporter->import($file);

            $successCount = count($excelImporter->assignments);
            $failedCount = count($excelImporter->failedRows);
            $failedFileUrl = null;


            if (!empty($excelImporter->assignments)) {
                // Deactivate old assignments
                $claimIds = array_column($excelImporter->assignments, 'claim_id');
                Assigning::whereIn('claim_id', $claimIds)->update(['is_active' => 0]);

                // Insert new assignments
                Assigning::insert($excelImporter->assignments);
            }

            // Export failed rows if any
            if (!empty($excelImporter->failedRows)) {
                $failedFileUrl = $this->exportFailedRows(
                    $excelImporter->failedRows,
                    $uploadedClaimBulkAssignFile->id
                );
            }

            // Update file status
            $uploadedClaimBulkAssignFile->uploaded_claims_status = UploadedClaimsStatus::INSERTED;
            $uploadedClaimBulkAssignFile->save();

            DB::commit();

            return [
                'success_count' => $successCount,
                'failed_count' => $failedCount,
                'file_record' => $uploadedClaimBulkAssignFile,
                'failed_file_url' => $failedFileUrl,
                'original_file_url' => route('download', ['key' => $originalFile->uuid]),
            ];

        } catch (Exception $e) {
            DB::rollBack();
            throw new NotExistsException('Failed to process bulk assign file: ' . $e->getMessage());
        }
    }

    /**
     * Export failed rows to Excel file
     */
    private function exportFailedRows(array $failedRows, int $fileId)
    {
        $relative = 'failedBulkAssigns/' . $fileId . '/failed.xlsx';
        $path     = 'public/' . $relative;

        Excel::store(
            new FailedBulkAssignExport($failedRows),
            $path,
            'local'
        );

        $upload = UploadedClaimsFile::findOrFail($fileId);
        $file = (new FilesUploader)->registerAndLinkFailedFile($upload, $relative, auth()->id());

        return route('download', ['key' => $file->uuid]);
    }

    public function list()
    {
        return Assigning::with(['fromUser', 'toUser', 'supervisorUser'])->paginate(10);
    }
}
