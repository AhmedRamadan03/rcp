<?php

namespace App\Http\Controllers\Api\V1\Claims;

use App\Enums\Roles;
use App\Exports\ClaimExports;
use App\Http\Resources\Api\V1\ClaimResource;
use App\Http\Resources\Api\V1\Collections\Collection;
use App\Models\Claim;
use App\Models\User;
use App\Services\AssigningService;
use App\Services\ClaimService;
use App\Services\ClaimsUploadUsingFileService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Maatwebsite\Excel\Excel as ExcelFacade;

class ClaimIndexController extends Controller
{
    public function __construct(
        public ClaimService $claimService,
        public AssigningService $assigningService,
        public ClaimsUploadUsingFileService $fileService
    ) {}

    /**
     * Display a listing of claims with filters.
     */
    public function index(Request $request)
    {
        $filters = $request->validate([
            'registered' => 'boolean',
            'assigned' => 'boolean',
            'approved' => 'boolean',
            'rejected' => 'boolean',
            'done' => 'boolean',
            'follow_up' => 'boolean',
            'manual' => 'boolean',
            'credited' => 'boolean',
            'law' => 'boolean',
            'delay' => 'boolean',
            'partial' => 'boolean',
            'closed' => 'boolean',
            'third_party' => 'boolean',
            'insured' => 'boolean',
            'created_start' => 'date',
            'created_end' => 'date',
            'accident_start' => 'date',
            'accident_end' => 'date',
            'user_id' => 'exists:users,id',
            'company_id' => 'exists:companies,id',
            'default_in_payment_by_system' => 'boolean',
            'default_in_payment' => 'boolean',
            'collector_id' => 'exists:users,id',
            'file_id' => 'exists:files,id',
            'paginate' => 'integer',
            'search_number' => 'string',
            'debtor_phone' => 'string',
            'page' => 'integer',
        ]);

        try {
            $claims = $this->claimService->list($filters, true, $filters['paginate'] ?? 100);

            return response()->json([
                "succes" => true,
                'message' => 'Claims list',
                'data' => new Collection($claims->items(), $message = 'claims list', $resourceClass = ClaimResource::class),
                'links' => $claims->links(),
                'collectors' => User::with('role')
                    ->whereIn('role_id', [
                        Roles::COLLECTOR->value,
                        Roles::EXTERNAL_OFFICE->value,
                        Roles::SUPERVISOR->value,
                        Roles::LEGAL_DEPARTMENT->value
                    ])
                    ->withTrashed()
                    ->get(),
            ]);
        } catch (Exception $e) {
            return response()->json([
                "succes" => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Assign claims to users.
     */
    public function assign(Request $request)
    {
        $request->validate([
            'claim_ids' => 'required|array',
            'user_ids' => 'required|array',
        ]);

        try {
            $this->assigningService->create([
                'claims_id' => $request->claim_ids,
                'supervisor_id' => auth()->id(),
                'to' => $request->user_ids,
                'from' => null,
            ]);

            return response()->json([
                "succes" => true,
                'message' => 'Claims assigned successfully',
            ]);
        } catch (Exception $e) {
            return response()->json([
                "succes" => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload claims from file.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'file|mimetypes:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/exports',
        ]);

        try {
            $result = $this->fileService->upload_file(auth()->user(), $request->file);

            return response()->json([
                "succes" => true,
                'message' => 'File processed successfully',
                'success_count' => $result[0]->count(),
                'failed_count' => $result[1],
                'failed_claims' => $result[2],
            ]);
        } catch (Exception $e) {
            return response()->json([
                "succes" => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export claims to Excel.
     */
    public function export(Request $request)
    {
        if (!auth()->user()->can('export', Claim::class)) {
            return response()->json([
                "succes" => false,
                'message' => __('claim.you_are_not_authorize_to_perform_this_action'),
            ], 403);
        }

        $filters = $request->all();

        try {
            $claims = $this->claimService->list($filters, true);

            return ExcelFacade::download(new ClaimExports($claims), 'claims.xlsx');
        } catch (Exception $e) {
            return response()->json([
                "succes" => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
