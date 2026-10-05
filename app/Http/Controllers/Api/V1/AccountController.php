<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DataHistoryEnum;
use App\Enums\FileSource;
use App\Enums\InvoiceStatus;
use App\Enums\UploadedClaimsStatus;
use App\Exports\ClaimsUpdateBilledTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\ExportAccountingReportRequest;
use App\Http\Resources\Api\V1\ClaimResource;
use App\Jobs\ProcessUpdateBilledClaimsOpenSpout;
use App\Models\Claim;
use App\Models\DataHistory;
use App\Models\UploadedClaimsFile;
use App\Services\ClaimService;
use App\Services\FilesUploader;
use App\Traits\ClaimFilters;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class AccountController extends Controller
{
    use ClaimFilters;
    public function __construct(public ClaimService $claimService) {}
    /**
     * Get calendar data including payment links and reminders
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(ExportAccountingReportRequest $request)
    {
        $perPage = $request->input('per_page', 10);
        $filters = $request->filters();
        $claims = $this->claimService->accountClaimList($filters, true, $perPage);
        return response()->json([
            'success' => true,
            'message' => "",
            'data' => ClaimResource::collection($claims->items()),
            'meta' => [
                'total' => $claims->total(),
                'per_page' => $claims->perPage(),
                'current_page' => $claims->currentPage(),
                'last_page' => $claims->lastPage(),
                'from' => $claims->firstItem(),
                'to' => $claims->lastItem(),
            ],
        ]);
    }

    public function updateClaims(Request $request)
    {
        $request->validate([
            'invoice_status' => 'required|in:' . implode(',', array_column(InvoiceStatus::cases(), 'value')),
            'ids'            => 'required|array',
            'ids.*'          => 'integer|exists:claims,id',
        ]);

        try {
            $invoiceDate = $request->invoice_status === InvoiceStatus::INVOICED->value
                ? Carbon::now()
                : null;

            Claim::whereIn('id', $request->ids)->update([
                'invoice_status' => $request->invoice_status,
                'invoice_date'   => $invoiceDate,
            ]);

            return $this->successResponse('Claims updated to ' . $request->invoice_status . ' successfully');
        } catch (\Exception $e) {
            return $this->apiErrorResponse($e->getMessage());
        }
    }


    public function update(Request $request)
    {
        $request->validate([
            'file'           => 'required|file|mimes:xlsx,xls',
            'invoice_status' => 'required|in:' . implode(',', array_column(InvoiceStatus::cases(), 'value')),
        ]);

        try {
            $originalFile = (new FilesUploader)->upload($request->file('file'), [
                'source'      => FileSource::UPLOADED_CLAIMS_FILES,
                'directory'   => 'uploaded-claims-file/' . Carbon::today()->year,
                'uploaded_by' => auth()->id(),
            ]);

            
            $uploadedFile = UploadedClaimsFile::create([
                'user_id'                => auth()->id(),
                'client_id'              => auth()->user()->client_id,
                'uploaded_claims_status' => UploadedClaimsStatus::SCHEDULED->value,
                'file_url'               => $originalFile->local_path ?? $originalFile->cloud_path,
                'original_file_id'       => $originalFile->id,
                'inserted_date'          => now(),
            ]);

            $history = DataHistory::createDataHistory(DataHistoryEnum::ACCOUNT_REPORT->value, DataHistoryEnum::IMPORT->value, $uploadedFile->file_url);

            ProcessUpdateBilledClaimsOpenSpout::dispatch($uploadedFile, auth()->user(), $request->invoice_status, $history->ref);

            return $this->successResponse('File uploaded successfully and queued for processing', [
                'file_id'     => $uploadedFile->id,
                'status'      => $uploadedFile->uploaded_claims_status->value,
                'uploaded_at' => $uploadedFile->inserted_date,
            ]);
           
        } catch (Exception $e) {
            return $this->apiErrorResponse($e->getMessage());
           
        }
    }

    public function downloadUpdateClaimsTemplate()
    {

        return
            Excel::download(new ClaimsUpdateBilledTemplateExport(), 'claims_update_billed_template.xlsx', \Maatwebsite\Excel\Excel::XLSX, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
    }


    public function exportOpenSpout(ExportAccountingReportRequest $request)
    {
        $fileName = 'accounting_export_' . time() . '.xlsx';
        $user     = auth()->user();
        $history = DataHistory::createDataHistory(DataHistoryEnum::ACCOUNT_REPORT->value, DataHistoryEnum::EXPORT->value, $fileName, $request->filters());
        \App\Jobs\ExportAccountingOpenSpout::dispatch($request->filters(), $user, $fileName, $history->ref, $request->columns ?? []);


        return $this->successResponse('Your file is being prepared. You will be notified once it is ready.');
      
    }
}
