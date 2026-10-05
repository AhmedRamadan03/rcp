<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ClaimStatus;
use App\Enums\Clients;
use App\Enums\DataHistoryEnum;
use App\Enums\DebtorType;
use App\Enums\ExportType;
use App\Enums\FileSource;
use App\Enums\LinkSendStatus;
use App\Enums\NotificationType;
use App\Enums\Roles;
use App\Enums\ReturnReason;
use App\Events\ClaimInfo\UpdateClaimInstallmentEvent;
use App\Exceptions\ImmutableClaimExternalReferenceException;
use App\Exceptions\ImmutableClaimReferenceException;
use App\Exports\ClaimExports;
use App\Exports\ClaimImportTemplateExport;
use App\Http\Requests\V1\Claims\CreateRequests\StoreClaimRequest;
use App\Http\Requests\V1\Claims\UpdateRequests\UpdateClaimRequest;
use App\Http\Resources\Api\V1\ClaimResource;
use App\Models\Claim;
use App\Models\ClaimStatusHistory;
use App\Models\DataHistory;
use App\Models\FailedImportedClaim;
use App\Models\PaymentLink;
use App\Models\Setting;
use App\Models\UploadedClaimsFile;
use App\Models\User;
use App\Services\AssigningService;
use App\Services\Claim\ClaimActionOptions;
use App\Services\ClaimImport\ClaimImportWithBacklogService;
use App\Services\ClaimService;
use App\Services\ClaimsUploadUsingFileService;
use App\Services\NotificationService;
use App\Services\FilesUploader;
use App\Services\ReferenceGenerationService;
use App\Traits\ClaimFilters;
use Exception;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ClaimController extends Controller
{
    use ClaimFilters;
    public function __construct(
        public ClaimService $claimService,
        public AssigningService $assigningService,
        public ClaimsUploadUsingFileService $fileService,
        private readonly ReferenceGenerationService $referenceService,
    ) {}
    /**
     * Get Claims data.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user =  auth()->user();
        $filters = $this->collectFilters($request);
        $claims = $this->claimService->list($filters, true, $request->paginate ?? 100, false, $user);

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

    public function show(Claim $claim)
    {
        return response()->json([
            'success' => true,
            'message' => 'Claim successfully shown.',
            'data' => new ClaimResource($claim),
        ]);
    }

    public function getRecoveryReasons(Request $request)
    {
        $reasons = [];
        foreach (ReturnReason::clientReasons() as $reason) {
            $reasons[] = [
                'value' => $reason->value,
                'description' => ReturnReason::getString($reason->value),
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Return reasons retrieved successfully.',
            'data' => $reasons
        ]);
    }

    public function store(StoreClaimRequest $request)
    {

        // Validate request
        $validated = $request->validated();

        
        //Check for duplicate claim 

        $isDuplicate = $this->claimService->isDuplicateClaim(
            $validated['claim_number'],
            $validated['debtor_nid'],
            $validated['amount'],
            $validated['sub_claim_number'] ?? null
        )->exists();

        if ($isDuplicate) {
            return response()->json([
                'success' => false,
                'message' => 'Claim with this claim number and sub-claim number already exists.',
            ], 422);
        }

       // External reference uniqueness is scoped per client :
        // NULL external_reference is always allowed (internal claims).
        if (!empty($validated['external_reference'])) {
            $isExternalReferenceExists = Claim::query()
                ->where('external_reference', $request->external_reference)
                ->exists();

                if ($isExternalReferenceExists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Claim with this external reference already exists.',
                    ], 422);
                }
        }

                // Add user/client context
        $user = auth()->user();

        $validated['client_id'] = $user->client_id;
        $validated['user_id'] = $user->id;
        $validated['return_reason'] = $request->return_reason_id;
        // Create the claim
        $claim = $this->claimService->create($validated);
        // generate refernce
        $this->referenceService->generateForNewClaim(function (string $reference) use ($claim) {
                $claim->reference = $reference;
                $claim->save();
            });

        // Auto-assign if user is collector or external office
        if (in_array($user->role_id, [Roles::COLLECTOR->value, Roles::EXTERNAL_OFFICE->value])) {
            $this->autoAssignClaimToUser($claim, $user);
        }

        return response()->json([
            'success' => true,
            'message' => 'Claim successfully created.',
            'data' => new ClaimResource($claim),
        ]);
    }

    public function update($id, UpdateClaimRequest $request)
    {
        try {

            $validated = $request->validated();
            $validated['return_reason'] = $request->return_reason_id;

            // Find claim
            $claim = Claim::find($id);

            if (!$claim) {
                return $this->errorResponse(message: 'Claim not found.', statusCode: 404);
            }

            $isPhoneChanged = isset($validated['debtor_phone']) && UpdateClaimRequest::normalizePhone($validated['debtor_phone'])  !== UpdateClaimRequest::normalizePhone($claim->debtor_phone);
             // check for duplication 
            $isAmountChanged = isset($request->amount) && $request->amount != $claim->amount;
            $checkAmount = $isAmountChanged ? $request->amount : ($validated['amount'] ?? $claim->amount);
            $subClaimNumber = $validated['sub_claim_number'] ?? $claim->sub_claim_number;

            $isDuplicate = $this->claimService->isDuplicateClaim(
                $validated['claim_number'],
                $validated['debtor_nid'],
                $checkAmount,
                $subClaimNumber
            )->where('id', '<>', $claim->id)->exists();

            if ($isDuplicate) {
                return $this->errorResponse(message: 'claim with this claim number and sub-claim number already exists.', statusCode: 422);
            }
            if (!empty($validated['external_reference'])) {
                $isExternalReferenceExists = Claim::query()
                    ->where('external_reference', $request->external_reference)
                    ->where('id', '<>', $claim->id)
                    ->exists();

                    if ($isExternalReferenceExists) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Claim with this external reference already exists.',
                        ], 422);
                    }
            }

            // If phone changed and claim was transferred to ELM, update status back to approved
            if ($isPhoneChanged && $claim->status == ClaimStatus::TRANSFER_TO_ELM->value) {
                
                $validated['status'] = ClaimStatus::APPROVED->value;
                $user_id = auth()->user()->id;

                $this->claimService->writeToClaimStatusHistory(
                    $claim->id,
                    previous_status: ClaimStatus::TRANSFER_TO_ELM->value,
                    current_status: ClaimStatus::APPROVED->value,
                    user_id: $user_id
                );
            }

            if ($isAmountChanged) {

                $changeAmountData = [
                    'new_amount' => $request->amount,
                    'current_amount' => $claim->amount,
                    'note' => null,
                ];

                unset($validated['amount']);

                $amountChangeResult = $this->claimService->changeAmount($claim, $changeAmountData, auth()->user(), ClaimActionOptions::systemApi());
                if (count($validated) > 0) {
                    $claim->update($validated);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Claim updated successfully with amount change.',
                    'data' => new ClaimResource($claim->fresh()),
                ]);
            }


            // Update claim
            $claim->update($validated);
            
            //fire event for claim info
            event(new UpdateClaimInstallmentEvent($claim->id));



            // Adjust related payment if necessary
            //$this->updatePaymentAmountIfNeeded($claim);

            // Return updated data
            return response()->json([
                'success' => true,
                'message' => 'Claim successfully updated.',
                'data' => new ClaimResource($claim),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function updateAmount(Request $request, $claim_id)
    {
        try {

            $claim = Claim::findOrFail($claim_id);

            $data = $request->validate([
                'new_amount' => 'required|numeric',
                'note' => auth()->user()->role_id == Roles::COLLECTOR->value || auth()->user()->role_id == Roles::EXTERNAL_OFFICE->value ? 'required|min:6' : 'nullable',
            ]);

            $data['current_amount'] = $claim->amount;

            $result = $this->claimService->changeAmount($claim, $data, auth()->user(), ClaimActionOptions::systemApi());



            return response()->json([
                'success' => true,
                'message' => $result
                    ? 'Claim amount updated successfully.'
                    : 'Status change request created successfully.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
    public function skipMsg(Request $request, $claim_id)
    {
        try {
            $request->validate([
                'skip_msg' => 'required|boolean',
            ]);
            $claim = Claim::findOrFail($claim_id);
            $claim->is_msg_skip = $request->skip_msg;
            $claim->save();
            return response()->json([
                'success' => true,
                'message' => 'Claim skip msg updated successfully.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
    public function proofDebtEnable(Request $request, $claim_id)
    {
        try {
            $request->validate([
                'proof_debt_enable' => 'required|boolean',
            ]);
            $claim = Claim::findOrFail($claim_id);
            $claim->proof_debt_enable = $request->proof_debt_enable;
            $claim->save();

            $setting = Setting::proofOfDebt()->value;
            $view = $setting == 1 ? 'public' : 'private';

            if ($view == 'private') {
                return response()->json([
                    'success' => false,
                    'message' => __('claim.proof_debt_disabled_message'),
                ], 400);
            }
            return response()->json([
                'success' => true,
                'message' => 'Claim Proof Debt Voucher is ' . ($claim->proof_debt_enable ? 'enabled' : 'disabled') . ' successfully.',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function toggleClientRequests(Request $request, Claim $claim)
    {
        try {
            $validated = $request->validate([
                'client_requests_enabled' => ['required', 'boolean'],
            ]);

            $claim->update([
                'client_requests_enabled' => $validated['client_requests_enabled'],
            ]);

            $claim->refresh();

             //fire event to fetch claim info in client view 
            event(new UpdateClaimInstallmentEvent($claim->id));

            return response()->json([
                'success' => true,
                'message' => 'Client requests status updated successfully',
                'data' => [
                    'client_requests_enabled' => $claim->client_requests_enabled,
                ]
            ]);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update client requests status',
            ], 400);
        }
    }

    public function import(Request $request)
    {

        if (auth()->user()->has_import_in_progress) {
            return response()->json([
                'success' => false,
                'message' => 'Can not import claims there is already import in progress.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(message: "Validation errors", errors: $validator->errors(), statusCode: 422);
        }

        try {


            $originalFile = (new FilesUploader)->upload($request->file('file'), [
                'source'      => FileSource::UPLOADED_CLAIMS_FILES,
                'directory'   => 'uploaded-claims-file/' . Carbon::today()->year,
                'uploaded_by' => auth()->id(),
            ]);

            // Create UploadedClaimsFile record with status "scheduled"
            $uploadedFile = \App\Models\UploadedClaimsFile::create([
                'user_id'                => auth()->id(),
                'client_id'              => auth()->user()->client_id,
                'uploaded_claims_status' => \App\Enums\UploadedClaimsStatus::SCHEDULED->value,
                'file_url'               => $originalFile->local_path ?? $originalFile->cloud_path,
                'original_file_id'       => $originalFile->id,
                'inserted_date'          => now(),
            ]);

            $history = DataHistory::createDataHistory(DataHistoryEnum::IMPORT_NEW_CLAIMS->value, DataHistoryEnum::IMPORT->value, $uploadedFile->file_url);
            // Dispatch ProcessClaimImport job
            \App\Jobs\ProcessClaimImportOpenSpout::dispatch($uploadedFile, auth()->user(), $history->ref);

            // Return immediate response with file_id and status
            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully and queued for processing',
                'data' => [
                    'file_id' => $uploadedFile->id,
                    'status' => $uploadedFile->uploaded_claims_status->value,
                    'uploaded_at' => $uploadedFile->inserted_date,
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                "success" => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function importResult(int $fileId)
    {
        $uploadedFile = \App\Models\UploadedClaimsFile::find($fileId);

        if (!$uploadedFile) {
            return response()->json([
                'success' => false,
                'message' => 'Import file not found'
            ], 404);
        }

        // Authorization check
        if ($uploadedFile->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You are not authorized to view this import'
            ], 403);
        }

        $data = [
            'file_id' => $uploadedFile->id,
            'status' => $uploadedFile->uploaded_claims_status->value,
            'uploaded_at' => $uploadedFile->inserted_date,
        ];

        if (in_array($uploadedFile->uploaded_claims_status, [
            \App\Enums\UploadedClaimsStatus::COMPLETED,
            \App\Enums\UploadedClaimsStatus::FAILED
        ])) {
            $data['completed_at'] = $uploadedFile->completed_at;
            if ($uploadedFile->uploaded_claims_status === \App\Enums\UploadedClaimsStatus::COMPLETED) {
                $data['success_count'] = $uploadedFile->success_count;
                $data['failed_count'] = $uploadedFile->failed_count;
                $data['original_file_url'] = route("download", [
                    "key" => $uploadedFile->originalFile?->uuid ?? $uploadedFile->file_url,
                ]);

                if ($uploadedFile->failed_file_path) {
                    $data['failed_claims_url'] = route("download", [
                        "key" => $uploadedFile->failedFile?->uuid ?? $uploadedFile->failed_file_path,
                    ]);
                }
            }
        }

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }


    //backlogImport


    public function backlogImport(Request $request)
    {

        if (auth()->user()->has_import_in_progress) {
            return response()->json([
                'success' => false,
                'message' => 'Can not import claims there is already import in progress.',
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx,xls|max:10240',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse(message: "Validation errors", errors: $validator->errors(), statusCode: 422);
        }

        try {


            $originalFile = (new FilesUploader)->upload($request->file('file'), [
                'source'      => FileSource::UPLOADED_CLAIMS_FILES,
                'directory'   => 'uploaded-claims-file/' . Carbon::today()->year,
                'uploaded_by' => auth()->id(),
            ]);

            // Create UploadedClaimsFile record with status "scheduled"
            $uploadedFile = \App\Models\UploadedClaimsFile::create([
                'user_id'                => auth()->id(),
                'client_id'              => auth()->user()->client_id,
                'uploaded_claims_status' => \App\Enums\UploadedClaimsStatus::SCHEDULED->value,
                'file_url'               => $originalFile->local_path ?? $originalFile->cloud_path,
                'original_file_id'       => $originalFile->id,
                'inserted_date'          => now(),
            ]);

            $history = DataHistory::createDataHistory(DataHistoryEnum::IMPORT_BACKLOG_CLAIMS->value, DataHistoryEnum::IMPORT->value, $uploadedFile->file_url);
            // Dispatch ProcessClaimImport job
            \App\Jobs\ProcessClaimImportWithActionOpenSpout::dispatch($uploadedFile, auth()->user(), $history->ref);

            // Return immediate response with file_id and status
            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully and queued for processing',
                'data' => [
                    'file_id' => $uploadedFile->id,
                    'status' => $uploadedFile->uploaded_claims_status->value,
                    'uploaded_at' => $uploadedFile->inserted_date,
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                "success" => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }


    public function downloadImportClaimsTemplate()
    {
        $clientName = config('app.client_name');
        $importTemplates = [
            Clients::GIG->value => public_path('templates/claims_import_template.xlsx'),
            Clients::GGI->value => public_path('templates/claims_import_template.xlsx'),
            Clients::MIC->value => public_path('templates/claims_import_template.xlsx'),
            Clients::ART->value => public_path('templates/ART/imports/art_claims_import_template.xlsx'),
        ];

        $path = $importTemplates[$clientName] ?? public_path('templates/claims_import_template.xlsx');
        if (!file_exists($path)) {
            return response()->json([
                "succes" => false,
                'message' => 'Template file not found',
            ], 404);
        }

        return response()->download($path, strtolower($clientName) . "_claims_import_template.xlsx");
    }


    public function AvailableClaimStatusTemplate()
    {
        $claimStatus = [
            ClaimStatus::APPROVED->value,
            ClaimStatus::CLOSED->value,
            ClaimStatus::MANUAL_COLLECTED->value,
            Claimstatus::PARTIAL->value,
            ClaimStatus::DELAY->value,
            ClaimStatus::LAW_FIRM->value,
            ClaimStatus::TRANSFER_TO_ELM->value,
        ];

        return response()->json([
            'success' => true,
            'message' => 'Claim status retrieved successfully.',
            'data' => $claimStatus,
        ]);
    }

    public function downloadBacklogTemplate(Request $request)
    {
        $clientName = config('app.client_name');

        $statuses = [
            ClaimStatus::APPROVED->value => 'backlog_approved_template.xlsx',
            ClaimStatus::CLOSED->value => 'backlog_closed_template.xlsx',
            ClaimStatus::MANUAL_COLLECTED->value => 'backlog_manaual_collected_template.xlsx',
            ClaimStatus::DELAY->value => 'backlog_delay_template.xlsx',
            ClaimStatus::PARTIAL->value => 'backlog_partial_template.xlsx',
            ClaimStatus::LAW_FIRM->value => 'backlog_to_law_template.xlsx',
            ClaimStatus::TRANSFER_TO_ELM->value => 'backlog_transfer_to_elm_template.xlsx',
        ];

        $validated = $request->validate([
            'claim_status' => ['required', Rule::in(array_keys($statuses))],
        ]);

        $fileName = $statuses[$validated['claim_status']];

        $clientTemplate = public_path("templates/{$clientName}/imports/backlog/{$fileName}");
        $defaultTemplate = public_path("templates/backlog/{$fileName}");

        $templatePath = file_exists($clientTemplate) ? $clientTemplate : $defaultTemplate;

        if (!file_exists($templatePath)) {
            return response()->json([
                'success' => false,
                'message' => 'Template file not found',
            ], 404);
        }

        return response()->download($templatePath, basename($templatePath));
    }

    /**
     * Export claims to Excel.
     */
    public function export(Request $request)
    {

        $fileName = 'claims_export_' . time() . '.xlsx';
        $filters = $this->collectFilters($request);



        $user = auth()->user();

        Excel::queue(
            new ClaimExports($filters, $user),
            'exports/' . $fileName,
            'public'
        )->chain([
            new \App\Jobs\MarkExportAsReady($fileName, $user, ExportType::EXPORT_CLAIMS),
        ]);


        return response()->json([
            'status' => true,
            'message' => 'Your file is being prepared. You will be notified once it is ready.',
        ]);
    }

    public function checkDuplicateClaim(Request $request)
    {
        $validated = $request->validate([
            'claim_number' => ['required'],
            'deptor_nid' => ['required'],
            'amount' => ['required'],
            'sub_claim_number' => ['nullable'],
            'exclude_id' => ['nullable'],
        ]);

        $query = $this->claimService->isDuplicateClaim(
            $validated['claim_number'],
            $validated['deptor_nid'],
            $validated['amount'],
            $validated['sub_claim_number'] ?? null
        );

        if (!empty($validated['exclude_id'])) {
            $query->where('id', '!=', $validated['exclude_id']);
        }

        $isDuplicate = $query->exists();

        return response()->json([
            'data' => [
                'is_duplicate' => $isDuplicate,
                'is_duplicate_message' => $isDuplicate ? 'A claim with the same details already exists.' : 'No duplicate claim found',
            ],
        ]);
    }
    public function checkExternalReferenceExists(Request $request)
{
    $validator = Validator::make($request->all(), [
        'external_reference' => ['required'],
        'claim_id' => ['nullable', 'integer'], // جديد: استقبال الـ claim الحالي عند التعديل
    ]);

    if ($validator->fails()) {
        return response()->json([
            'message' => 'The given data was invalid.',
            'errors' => $validator->errors(),
        ], 422);
    }

    $query = Claim::withTrashed() // مطابقة سلوك الـ unique index في الداتابيز
        ->where('external_reference', $request->external_reference);

    if ($request->filled('claim_id')) {
        $query->where('id', '<>', $request->claim_id); // استثناء الـ claim الحالي عند التعديل
    }

    $isExternalReferenceExists = $query->exists();

    // رد طبيعي في الحالتين، من غير أي throw
    return response()->json([
        'exists' => $isExternalReferenceExists,
        'message' => $isExternalReferenceExists ? 'External reference already exists.' : null,
    ], $isExternalReferenceExists ? 409 : 200);
}
    /**
     * Export claims to Excel using OpenSpout (memory-efficient, large-dataset friendly).
     */
    public function exportOpenSpout(Request $request)
    {
        $fileName = 'claims_export_' . time() . '.xlsx';
        $filters  = $this->collectFilters($request);
        $user     = auth()->user();

        $history = DataHistory::createDataHistory(DataHistoryEnum::CLAIM_EXPORT->value, DataHistoryEnum::EXPORT->value, $fileName,$filters);

        \App\Jobs\ExportClaimsOpenSpout::dispatch($filters, $user, $fileName, false, $history->ref);

        return response()->json([
            'status'  => true,
            'message' => 'Your file is being prepared. You will be notified once it is ready.',
        ]);
    }


    public function rules($request, $action)
    {
        $rules = [
            'claim_number' => ['required'],
            'debtor_name' => ['required'],
            'debtor_type' => ['required'],
            'debtor_nid' => ['required'],
            'accident_date' => ['required'],
            'city_id' => ['sometimes', 'exists:cities,id'],
            'type' => ['required'],
            'remark' => ['nullable', 'min:6'],
            'return_reason_id' => ['required_if:debtor_type,' . DebtorType::INSURED->value],
            'mistake_percentage' => ['required'],
            'plate_number' => ['sometimes'],
            'victim_plate_number' => ['sometimes'],
            'car_type' => ['sometimes'],
            'sub_claim_number' => ['nullable'],
            'external_reference' => ['nullable'],
        ];


        if ($action === 'store') {
            $rules['amount'] = ['required', 'numeric'];
            $rules['city_id'] = ['sometimes', 'exists:cities,id'];
        }

        if ($action === 'update') {
            $user = auth()->user();

            $rules['amount'] = ['sometimes', 'numeric'];
            $rules['city_id'] = ['nullable', 'exists:cities,id'];
        }

        // Optional intimation date validation
        if (!empty($request->intimation_date)) {
            $rules['intimation_date'] = ['date'];
        } else {
            $rules['intimation_date'] = ['sometimes'];
        }

        // Client-specific rules
        if (config('app.client_name') == Clients::GGI->value) {
            $rules['sub_claim_number'] = ['required'];
            $rules['policy_number'] = ['required'];
        } else {
            $rules['accident_number'] = ['required'];
            $rules['policy_number'] = ['sometimes'];
        }

        // ELM-related phone validation
        if (config('app.elm.enabled')) {
            $rules['debtor_phone'] = [
                'nullable',
                Rule::when($request->filled('debtor_phone'), 'min:10')
            ];
        } else {
            $rules['debtor_phone'] = ['nullable'];
        }

        return $rules;
    }

    /**
     * Assign claim to user and send notification.
     */
    protected function autoAssignClaimToUser($claim, $user)
    {
        (new AssigningService)->create([
            'claims_id' => [$claim->id],
            'supervisor_id' => $user->id,
            'to' => [$user->id],
            'from' => $user->id,
            'is_active' => 1,
        ]);

        NotificationService::create([
            'title_ar' => 'تم اسناد المطالبة بالرقم ' . $claim->claim_number . ' لك.',
            'title_en' => 'The Claim number ' . $claim->claim_number . ' assigned to you',
            'description_ar' => 'تم اسناد المطالبة التي ادخلتها وتحمل الرقم ' . $claim->claim_number . ' لك اليآ',
            'description_en' => 'Claim number (' . $claim->claim_number . '), you just created has been assigned to you automatically',
            'regarding' => NotificationType::CLAIM_ASSIGNED->value,
            'user_id' => $user->id,
            'to' => json_encode([$user->id]),
            'links' => json_encode([
                [
                    'label_ar' => 'عرض تفاصيل الطلب',
                    'label_en' => 'Open Claim Info',
                    'url' => 'claims/' . $claim?->id . '/info',
                ],
            ]),
        ]);
    }

    private function errorResponse(string $message = null, $data = [], $errors = [], int $statusCode = 400)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => $data,
            'errors' => $errors,
        ], $statusCode);
    }

    private function updatePaymentAmountIfNeeded(Claim $claim)
    {
        $payment = $claim->payments->first();

        if ($payment && $claim->amount != $payment->amount) {
            $previousAmount = $payment->amount;

            $payment->update(['amount' => $claim->amount]);

            $link = $payment->links()
                ->whereIn('send_status', [
                    LinkSendStatus::SENT->value,
                    LinkSendStatus::NOT_SENT->value,
                ])
                ->first();

            if ($link) {
                $link->update([
                    'amount' => ($claim->amount - $previousAmount) + $link->amount,
                ]);
            }
        }
    }



    private function subClaimNumberExists(?int $claimId, ?string $subClaimNumber): bool
    {
        // Only check if subClaimNumber is provided and client is GGI
        if (!$subClaimNumber || config('app.client_name') !== Clients::GGI->value) {
            return false;
        }

        return Claim::where('sub_claim_number', $subClaimNumber)
            ->when($claimId, fn($query) => $query->where('id', '!=', $claimId))
            ->exists();
    }
}