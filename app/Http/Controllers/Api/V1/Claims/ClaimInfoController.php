<?php

namespace App\Http\Controllers\Api\V1\Claims;

use App\Enums\AdditionalFileType;
use App\Enums\ClaimFileAccess;
use App\Enums\ClaimInsertionType;
use App\Enums\ClaimStatus;
use App\Enums\CloudStorageDisk;
use App\Enums\DataHistoryEnum;
use App\Enums\ElmTemplates;
use App\Enums\FileSource;
use App\Enums\FileVisibility;
use App\Enums\LinkSendStatus;
use App\Enums\MessageTemplates;
use App\Enums\NotificationType;
use App\Enums\PaymentStatus;
use App\Enums\PermissionRequestsStatus;
use App\Enums\RequestStatus;
use App\Enums\Roles;
use App\Enums\UploadedClaimsStatus;
use App\Events\ClaimInfo\UpdateClaimInstallmentEvent;
use App\Exceptions\NotAuthorizeException;
use App\Exports\ClaimsBulkAssignTemplateExport;
use App\Helpers\PaymentLinkHelper;
use App\Http\Requests\V1\Claims\CreateRequests\ClaimCommentStoreRequest;
use App\Http\Requests\V1\Claims\ExportClaimTransactionsRequest;
use App\Http\Requests\V1\Claims\UpdateRequests\CancelDiscountRequest;
use App\Jobs\ExportClaimTransactionsOpenSpout;
use App\Jobs\ProcessBulkAssignClaimsOpenSpout;
use App\Models\AdditionalFile;
use App\Models\Assigning;
use App\Models\Claim;
use App\Models\ClaimComment;
use App\Models\ClaimFile;
use App\Models\ClaimStatusHistory;
use App\Models\CloseReason;
use App\Models\ElmRequest;
use App\Models\Ivr;
use App\Models\LoginAttempt;
use App\Models\Message;
use App\Models\Payment;
use App\Models\PaymentLink;
use App\Models\PaymentTransaction;
use App\Models\StatusChangeRequest;
use App\Models\UploadedClaimsFile;
use App\Models\User;
use App\Services\AssigningService;
use App\Services\ClaimService;
use App\Services\DiscountService;
use App\Services\ElmService;
use App\Services\FilesUploader;
use App\Services\IvrService;
use App\Services\MaqsamService;
use App\Services\MessageService;
use App\Services\NotificationService;
use App\Services\PaymentService;
use App\Services\SettingsService;
use App\Services\SmsService;
use App\Http\Resources\Api\V1\ClaimResource;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use App\Http\Requests\V1\Claims\CreateRequests\MakeDiscountRequest;
use App\Http\Requests\V1\Claims\MakeInstallmentRequest;
use App\Http\Requests\V1\Claims\PayInstallmentRequest;
use App\Http\Resources\Api\V1\AssignClaims\AssignedHistoryResource;
use App\Http\Resources\Api\V1\AssignClaims\AssigniesHistoryResource;
use App\Traits\ApiResponse;
use App\Http\Resources\Api\V1\Collections\Collection;
use App\Models\DataHistory;
use App\Models\PaymentRequest;
use App\Services\Claim\BackToOriginalStatusService;
use App\Services\Claim\ClaimActionOptions;
use App\Services\Claim\CloseClaimService;
use App\Services\Claim\InstallmentService;
use App\Services\Claim\StatusActions\ClaimStatusActionResolver;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class ClaimInfoController extends Controller
{
    use ApiResponse;

    public function __construct(
        public ClaimService        $claimService,
        public AssigningService    $assigningService,
        public FilesUploader       $filesUploader,
        public NotificationService $notificationService,
        public MessageService      $messageService,
        public IvrService          $ivrService,
        public MaqsamService       $maqsamService,
        public SmsService          $smsService,
        public CloseClaimService   $closeClaimService,
        public BackToOriginalStatusService   $backToOriginalStatusService,
        public ClaimStatusActionResolver   $statusActionResolver,
        public InstallmentService   $installmentService,
        //public MaqsamService       $maqsamService
    ) {}

    /**
     * Get claim information.
     */
    public function show($claimId)
    {
        $claim = Claim::findOrFail($claimId);

        return response()->json([
            'success' => true,
            'message' => 'Claim information',
            'data' => new ClaimResource($claim),
        ]);
    }

    public function claimFiles($claimId)
    {
        $claimFiles = ClaimFile::where('claim_id', $claimId)->get();

        return response()->json([
            'success' => true,
            'message' => 'Claim Files information',
            'data' => $claimFiles,
        ]);
    }

    public function claimAdditionalFiles($claimId)
    {
        $claimAdditionalFiles = AdditionalFile::where('claim_id', $claimId)
            ->where(function ($query) {
                $query
                    ->whereHas('statusChangeRequest', function ($q) {
                        $q->where('request_status', RequestStatus::APPROVED->value);
                    })
                    ->orWhere('attachment_status', FileVisibility::PUBLISHED->value);
            })
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Claim Additional Files information',
            'data' => $claimAdditionalFiles,
        ]);
    }

    public function claimCalls($claimId)
    {
        $calls = Ivr::whereHas('paymentLink', function ($query) use ($claimId) {
            $query->whereHas('payment', function ($query) use ($claimId) {
                $query->where('claim_id', $claimId);
            });
        })->get();

        return response()->json([
            'success' => true,
            'message' => 'Claim Calls information',
            'data' => $calls,
        ]);
    }

    public function claimComments($claimId)
    {
        $comments = ClaimComment::where('claim_id', $claimId)->get();

        return response()->json([
            'success' => true,
            'message' => 'Claim Comments information',
            'data' => $comments,
        ]);
    }

    public function createClaimComment(ClaimCommentStoreRequest $request, $claimId)
    {
        $claim = $claim = Claim::findOrFail($claimId);



        $validatedData = $request->validated();
        $comment = new ClaimComment();
        $comment->claim_id = $claim->id;
        $comment->user_id = auth()->id();
        $comment->body = $validatedData['body'];
        $comment->reminder_date = $validatedData['reminder_date'] ?? null;
        $comment->is_court_reminder = (int) ($validatedData['is_court_reminder'] ?? 0);
        $comment->save();

        if ($claim->status == ClaimStatus::APPROVED->value) {
            $claim->status = ClaimStatus::FOLLOW_UP->value;
            $claim->save();
        }


        return response()->json([
            'success' => true,
            'message' => 'Claim comment added successfully',
            'data' => $comment,
        ]);
    }


    public function claimPayments($claimId)
    {

        $payments = Payment::where('claim_id', $claimId)->latest()->first();

        return response()->json([
            'success' => true,
            'message' => 'Claim Payments information',
            'data' => $payments,
        ]);
    }

    public function claimPaymentLinks($claimId)
    {

        $paymentLinks = PaymentLink::whereHas('payment', function ($query) use ($claimId) {
            $query->where('claim_id', $claimId);
        })->where("send_status", "!=", LinkSendStatus::CANCELED)
            ->orderBy('order', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Claim Payment Links information',
            'data' => $paymentLinks,
        ]);
    }

    public function getCanceledLinks($claimId)
    {

        $paymentLinks = PaymentLink::whereHas('payment', function ($query) use ($claimId) {
            $query->where('claim_id', $claimId);
        })->where('send_status', LinkSendStatus::CANCELED)->get();

        return response()->json([
            'success' => true,
            'message' => 'Claim Payment Links information',
            'data' => $paymentLinks,
        ]);
    }

    // write a function to get the claim transaction which belongs to PaymentLinks which belongs to Payments which belong to the Claim
    public function claimTransactions(ExportClaimTransactionsRequest $request,$claimId)
    {
        try {
            $validated = $request->filters();

            $query = PaymentTransaction::filter($validated)->whereHas('link', function ($query) use ($claimId, $validated) {
                if (isset($validated['payment_link_id'])) {
                    $query->where('id', $validated['payment_link_id']);
                }
                $query->whereHas('payment', function ($query) use ($claimId) {
                    $query->where('claim_id', $claimId);
                });
            });

            $transactions = $query->get();

            return response()->json([
                'success' => true,
                'message' => 'Claim Transactions information',
                'data' => $transactions,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function exportClaimTransactions(ExportClaimTransactionsRequest $request, $claimId)
    {
        $fileName = 'claim_transactions_' . $claimId . '_' . time() . '.xlsx';

        $filters = $request->filters();

        $history = DataHistory::createDataHistory(
            DataHistoryEnum::EXPORT_TRANSACTION_HISTORY->value,
            DataHistoryEnum::EXPORT->value,
            $fileName,
            $filters
        );

        ExportClaimTransactionsOpenSpout::dispatch(
            auth()->user(),
            (int) $claimId,
            $fileName,
            $history->ref,
            $request->columns(),
            $filters
        );

        return $this->successResponse('Your file is being prepared. You will be notified once it is ready.');
    }

    // write a function to get the claim status histroy which belongs to the claim
    public function claimStatusHistory($claimId)
    {

        $statusHistory = ClaimStatusHistory::with('file')->where('claim_id', $claimId)->get();

        return response()->json([
            'success' => true,
            'message' => 'Claim Status History information',
            'data' => $statusHistory,
        ]);
    }


    // claim login attempts
    public function claimLoginAttempts($claimId)
    {
        $loginAttempts = LoginAttempt::where('claim_id', $claimId)->get();

        return response()->json([
            'success' => true,
            'message' => 'Claim Login Attempts information',
            'data' => $loginAttempts,
        ]);
    }

    // write a function to get the assigned users which belongs to the claim
    public function claimAssignedUsers(Request $request, $claimId)
    {
        $per_page = $request->query('per_page', 10);
        $filters = $request->only(['user', 'supervisor']);
        $assignedUsers = Assigning::filter($filters)->with('fromUser','supervisorUser')->where('claim_id', $claimId)
            ->latest()
            ->paginate($per_page);

        
        return response()->json(
            new Collection($assignedUsers, 'Claim Assigned Users information', AssignedHistoryResource::class)
        );
    }

    // write a function to accept a claim using @ClaimService accept function
    public function claimAccept($claimId)
    {

        try {
           
            $claim = Claim::find($claimId);

            $action = $this->statusActionResolver->resolve(ClaimStatus::APPROVED->value);

            $action->handle(
                claim: $claim,
                item: [],
                actingUserId: auth()->id(),
                options: ClaimActionOptions::systemApi(),
            );
            return response()->json([
                'success' => true,
                'message' => 'Claim Accepted Successfully',
                'data' => $claim,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 422);
        }
    }

    public function linkBankTransfer($claimId, $linkId, PayInstallmentRequest $request)
    {
        try {

            $validatedData = $request->validated();

            


            $claim = Claim::find($claimId);

           

            try {
                $this->installmentService->payInstallment(
                    claim: $claim,
                    linkId: $linkId,
                    data: $request->validated(),
                    actingUserId: auth()->id()
                );

                 return response()->json([
                        'success' => true,
                        'message' => __('claim.claim_has_been_collected_manually_successfully'),
                        'data' => null,
                    ]);
            } catch (Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                ], 400);
            }
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function generateVoucher(Request $request, $claimId)
    {
        $claim = Claim::find($claimId);

        (new PaymentService)->generateObligationLetter($claim, auth()->user());

        return response()->json([
            'success' => true,
            'message' => 'Voucher Generated Successfully',
        ]);
    }
    // generate voucher for paymentLink using @ClaimService generateVoucher function only if the link send_status is PAID
    public function linkGenerateVoucher(Request $request, $claimId, $linkId)
    {
        try {
            $user = auth()->user();
            $canGenerateLetters = SettingsService::canGenerateLetters();

            $claimService = new ClaimService;
            $paymentLink = PaymentLink::find($linkId);

            if ($paymentLink->send_status !== LinkSendStatus::PAID->value || !$canGenerateLetters) {
                return response()->json([
                    'success' => false,
                    'message' => 'You Can not Perform This action',
                    'data' => $paymentLink,
                ], 400);
            }

    
            $request->validate([
                'note' => needs_supervisor_approval('claims', 'generate_voucher_letter', $user) ? 'required|min:6' : 'nullable',
            ]);

            $request->merge(['link_id' => $linkId]);

            if (!$claimService->createRequestStatus(
                    $claimId,
                    PermissionRequestsStatus::GENERATE_VOUCHER_LETTER->value,
                    $request->all(),
                    null,
                    'generate_voucher_letter',
                    'claims'
                )
            ) {

                $claimService->generateVoucher($linkId);

                //fire event for claim info

                event(new UpdateClaimInstallmentEvent($paymentLink->payment?->claim_id));
                return response()->json([
                    'success' => true,
                    'message' => 'Voucher Generated Successfully',
                    'data' => $paymentLink,
                ]);
            }
            return response()->json([
                'success' => true,
                'message' => __('claim.request_has_been_sent_successfully'),
                'data' => null,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 400);
        }
    }

    // send voucher for paymentLink using @ClaimService sendVoucher function only if the link send_status is PAID and voucher is generated
    public function linkSendVoucher(Request $request, $claimId, $linkId)
    {
        try {
            $user = auth()->user();
            $claimService = new ClaimService;
            $paymentLink = PaymentLink::find($linkId);

            if ($paymentLink->send_status != LinkSendStatus::PAID->value || $paymentLink->voucher == null) {
                return response()->json([
                    'success' => false,
                    'message' => 'You Have to generate Voucher First',
                    'data' => $paymentLink,
                ], 400);
            }

            $requiresApproval = in_array($user->role_id, [
                Roles::COLLECTOR->value,
                Roles::EXTERNAL_OFFICE->value
            ]) && !$user->can('send_discharge_letter', Claim::class);

            $request->validate([
                'note' => $requiresApproval ? 'required|min:6' : 'nullable',
            ]);

            $request->merge(['link_id' => $linkId]);

            if (
                $user->can('send_discharge_letter', Claim::class)
                || !$claimService->createRequestStatus(
                    $claimId,
                    PermissionRequestsStatus::SEND_VOUCHER_LETTER->value,
                    $request->all()
                )
            ) {
                $claimService->getVoucher($linkId);
                return response()->json([
                    'success' => true,
                    'message' => 'Voucher Sent Successfully',
                    'data' => $paymentLink,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => __('claim.request_has_been_sent_successfully'),
                'data' => null,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 400);
        }
    }

    // write function to generate Discharge letter for claim if the claim is paid make it the same as @claimInfo generate_dischage_letter function
    public function generateDischargeLetter(Request $request, $claimId)
    {
        try {

            $user = auth()->user();

            $request->validate([
                'note' => needs_supervisor_approval('claims', 'generate_discharge_letter', $user) ? 'required|min:6' : 'nullable',
            ]);

            $claimService = new ClaimService;

            if ( !$claimService->createRequestStatus($claimId, PermissionRequestsStatus::GENERATE_DISCHARGE_LETTER->value, $request,null,'generate_discharge_letter','claims')
            ) {
                $claim = $claimService->generateDischargeLetter($claimId);

                return response()->json([
                    'success' => true,
                    'message' => 'Discharge Letter Generated Successfully',
                    'data' => [
                        'id' => $claim->discharge->id,
                        'url' => route('download', ['key' => $claim->discharge->file?->uuid ?? $claim->discharge->url]),
                        'is_valid' => $claim->discharge->is_valid
                    ]
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => __('claim.request_has_been_sent_successfully'),
                'data' => null,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    // write function to download Discharge letter for claim if the claim is paid make it the same as @claimInfo download_dischage_letter function
    public function downloadDischargeLetter(Request $request, $claimId)
    {
        try {

            $claimService = new ClaimService;
            $result = $claimService->getDischargeLetter($claimId);
            return response()->json([
                'success' => true,
                'message' => 'Discharge Letter Downloaded Successfully',
                'data' => $result,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function sendDischargeLetter(Request $request, $claimId)
    {
        try {

            $user = auth()->user();

           
            $request->validate([
                'note' => needs_supervisor_approval('claims', 'send_discharge_letter', $user)  ? 'required|min:6' : 'nullable',
            ]);

            $claimService = new ClaimService;

            if (!$claimService->createRequestStatus($claimId, PermissionRequestsStatus::SEND_DISCHARGE_LETTER->value, $request,null,'send_discharge_letter','claims')
            ) {
                $result = $claimService->sendDischargeLetter($claimId);

                return response()->json([
                    'success' => true,
                    'message' => 'Discharge Letter Sent Successfully',
                    'data' => $result,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => __('claim.request_has_been_sent_successfully'),
                'data' => null,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function claimInstallments($claimId)
    {
        try {
            $installmentData = $this->claimService->getClaimInstallments($claimId);

            return response()->json([
                'success' => true,
                'message' => 'Claim installments retrieved successfully',
                'data' => $installmentData
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Claim not found',
                'data' => null
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null
            ], 400);
        }
    }

    // claimId installments ["id" , "amount", "date", "order"] note
    // write a function to make installments for claim the request must have installment json array which consist of optional id and required amount and date and order
    public function makeInstallments(MakeInstallmentRequest $request, $claimId)
    {
        try {
            $claim = Claim::findOrFail($claimId);

            $result = $this->installmentService->create(
                claim: $claim,
                installments: $request->validated('installments'),
                note: $request->validated('note')??null,
                actingUserId: auth()->id(),
                options: ClaimActionOptions::systemApi()
            );

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Installments Created Successfully',
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => __('claim.request_has_been_sent_successfully'),
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 400);
        }
    }

    
    /**
     * Update installments for a claim.
     * This endpoint allows updating existing installments and adding new ones.
     * Installments with status PAID or FAILED cannot be updated.
     *
     * The endpoint will:
     * - Update existing installments (when id is provided and > 0) ud represent payment link
     * - Create new installments (when id is 0 or omitted)
     * - Cancel any existing installments not included in the request
     * - Validate that installment amounts sum to the payment total
     *
     *
     * @param Request $request
     * @param int $claimId
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateInstallments(MakeInstallmentRequest $request, $claimId)
    {
        try {
            $claim = Claim::findOrFail($claimId);

            
          
            $result = $this->installmentService->update( 
                claim: $claim,
                installments: $request->validated('installments'),
                note: $request->validated('note')??null,
                actingUserId: auth()->id(),
                options: ClaimActionOptions::systemApi());

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Installments updated successfully',
                    'data' => $claim->fresh(),
                ]);
            } else {
                return response()->json([
                    'success' => true,
                    'message' => __('claim.request_has_been_sent_successfully'),
                    'data' => null,
                ]);
            }
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 400);
        }
    }

    /**
     * Delete installments from a claim.
     * Can only delete if there are no paid installments in the entire payment.
     * Cancels all payment links and converts to FULL_PAYMENT.
     *
     * @param Request $request
     * @param int $claimId
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteInstallments(Request $request, $claimId)
    {


        $claim = Claim::find($claimId);

       


        try {

            

            
            $result = $this->installmentService->delete( 
                claim: $claim,
                actingUserId: auth()->id(),
                options: ClaimActionOptions::systemApi());

            if ($result) 
            {
                return response()->json([
                    'success' => true,
                    'message' => 'Installments deleted successfully',
                    
                ]);
            }else{
                return response()->json([
                    'success' => false,
                     'message' => __('claim.request_has_been_sent_successfully'),
                ], 200);
            }
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 400);
        }
    }


    /**
     * Shared validation rules for installment claim state checks.
     */
    private function validateClaimForInstallments($claim)
    {
        $invalidStatuses = [
            ClaimStatus::REGISTERED->value,
            ClaimStatus::ASSIGNED->value,
            ClaimStatus::REJECTED->value,
        ];

        if (in_array($claim->status, $invalidStatuses)) {
            return "Installments cannot be created when claim status is {$claim->status}.";
        }

        if ($claim->status === ClaimStatus::TRANSFER_TO_ELM->value && !$claim->debtor_phone) {
            return "Installments cannot be created when claim status is {$claim->status}, and debtor phone is empty.";
        }

        if ($claim->is_collected) {
            return 'Installments cannot be created because the claim is already collected.';
        }

        if ($claim->is_close == 1) {
            return 'Installments cannot be created because the claim is closed.';
        }

        return null;
    }

    // write a function to make delay payment for claim the request must have the note and the due_date
    public function makeDelayPayment(Request $request, $claimId)
    {
        $user = auth()->user();
        $request->validate([
            'note' => needs_supervisor_approval('payments', 'delay_payments', $user)  ? 'required|min:6' : 'nullable',
            'date_to_delay' => 'required|date',
        ]);

        $claim = Claim::find($claimId);
        if (
            $claim->status === ClaimStatus::REGISTERED->value ||
            $claim->status === ClaimStatus::ASSIGNED->value ||
            $claim->status === ClaimStatus::REJECTED->value ||
            $claim->status === ClaimStatus::TRANSFER_TO_ELM->value ||
            $claim->is_collected ||
            $claim->is_close == 1
        ) {
            return response()->json([
                'success' => false,
                'message' => 'You Can not perform this action',
                'data' => null,
            ], 400);
        }
        $payment = $claim->payments->first();
        $reminding_amount = $payment->amount - $payment->paid_amount;
        $request->merge(["remaining_amount" => $reminding_amount]);

        try {
            $claimService = new ClaimService;


            if ($claimService->makeDelay($claim, $request->all(), $payment->id, $request->note ?? '')) {
                return response()->json([
                    'success' => true,
                    'message' => 'Delay Payment Created Successfully',
                    'data' => $claim,
                ]);
            } else {
                return response()->json([
                    'success' => true,
                    'message' => 'Request Have Been Sent Successfully',
                    'data' => $claim,
                ]);
            }
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 400);
        }
    }

    // write a function to make discount for claim the request must have amount_after_deduction, percentage, deducted_amount, note call @DiscountService addDiscount function
    public function makeDiscount(MakeDiscountRequest $request, $claimId)
    {
        try {

            $claim = Claim::findOrFail($claimId);

            $discountData = array_merge($request->validated(), [
                "claim_id" => $claimId,
                "payment_id" => $claim->payments->first()->id,
                "total_amount" => $claim->payments->first()->amount,
            ]);

            $discountService = new DiscountService;
            $discountService->addDiscount($discountData);

            return response()->json([
                'success' => true,
                'message' => 'Discount Created Successfully',
                'data' => $claim,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Claim not found',
                'data' => null,
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => null,
            ], 400);
        }
    }

    public function cancelDiscount(CancelDiscountRequest $request, $claimId)
    {
        try {
            $claim = Claim::findOrFail($claimId);

            (new DiscountService)->cancelDiscount($claim->activeDiscount);

            return response()->json([
                'success' => true,
                'message' => 'Discount Cancelled Successfully',
                'data' => $claim->load('activeDiscount'),
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Claim not found',
                'data' => null,
            ], 404);
        } catch (Exception $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'data' => null,
            ], 400);
        }
    }

    //write a function to transfer claim to legal firm
    public function transferClaimToLegal(Request $request, $claimId)
    {
        try {

            $claim = Claim::find($claimId);
            $action = $this->statusActionResolver->resolve(ClaimStatus::LAW_FIRM->value);

            $result = $action->handle(
                claim: $claim,
                item: $request->all(),
                actingUserId: auth()->id(),
                options: ClaimActionOptions::systemApi(),
                statusFile: $request->file('legal_file'),
            );

            return response()->json([
                'success' => true,
                'message' => $result == "" ? __('claim.request_has_been_sent_successfully') : __('claim.claim_has_been_transferred_to_legal_department_successfully'),
                'data' => $claim
            ]);
        
        } catch (Exception $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'data' => $claim
            ], 400);
        }
    }

    public function goBackToOriginalStatus(Request $request, $claimId)
    {
        try {
            $claim = Claim::findOrFail($claimId);
            $action = $this->statusActionResolver->resolve(ClaimStatus::BACK_TO_ORIGINAL_STATUS->value);

            $result = $action->handle(
                claim: $claim,
                item: $request->all(),
                actingUserId: auth()->id(),
                options: ClaimActionOptions::systemApi(),
            );

            
            return response()->json([
                'success' => true,
                'message' => $result == "" ? __('claim.request_has_been_sent_successfully') : __('claim.status_has_been_changed_successfully'),

                'data' => $claim,
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
            ], 400);
        }
    }

    public function defaultOnPayment(Request $request, $claimId)
    {
        try {
            $claim = Claim::find($claimId);
            $action = $this->statusActionResolver->resolve(ClaimStatus::DEFAULT_IN_PAYMENT->value);

            $result =$action->handle(
                claim: $claim,
                item: $request->all(),
                actingUserId: auth()->id(),
                options: ClaimActionOptions::systemApi(),
            );
            
                return response()->json([
                    'success' => true,
                    'message' => $result == "" ? __('claim.request_has_been_sent_successfully') : __('claim.claim_has_been_converted_to_default_on_payment_successfully'),
                    'data' => $claim
                ]);
           
        } catch (Exception $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'data' => $claim
            ], 400);
        }
    }

    public function claimBankTransfer(Request $request, $claimId)
    {
        try {
            $claim = Claim::findOrFail($claimId);
    
            $action = $this->statusActionResolver->resolve(ClaimStatus::MANUAL_COLLECTED->value);
    
            $action->handle(
                claim: $claim,
                item: $request->all(),
                actingUserId: auth()->id(),
                options: ClaimActionOptions::systemApi(),
                statusFile: $request->file('invoice_file'),
            );
    
            event(new UpdateClaimInstallmentEvent($claim->id));
    
            return response()->json([
                'success' => true,
                'message' => __('claim.claim_has_been_collected_manually_successfully'),
                'data' => $claim->fresh(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
            ], 400);
        }
    }

    public function claimCredited(Request $request, $claimId)
    {
        try {
            $claim = Claim::find($claimId);
           
            $action = $this->statusActionResolver->resolve(ClaimStatus::CREDITED->value);
    
            $result = $action->handle(
                claim: $claim,
                item: $request->all(),
                actingUserId: auth()->id(),
                options: ClaimActionOptions::systemApi(),
            );

          

            return response()->json([
                'success' => true,
                'message' => $result == "" ? __('claim.request_has_been_sent_successfully') : __('claim.claim_has_been_credited_successfully'),
                'data' => $claim
            ]);
            

        } catch (Exception $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'data' => $claim
            ], 400);
        }
    }

    // public function transferClaimToElm(Request $request, $claimId)
    // {
    //     try {
    //         $claim = Claim::find($claimId);

    //         if ($claim->status !== ClaimStatus::APPROVED->value && $claim->status == ClaimStatus::FOLLOW_UP->value) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => "You Can Not Perform This Action",
    //                 'data' => $claim
    //             ]);
    //         }

    //         $paymentLink = $claim->payments->first()->links->first();

    //         $length = strlen($paymentLink->link);

    //         if ($length > 6) {
    //             // Update the existing payment link status to CANCELED
    //             $paymentLink->send_status = LinkSendStatus::CANCELED->value;
    //             $paymentLink->save();

    //             // Create a new payment link with the 6-character link
    //             $newPaymentLink = PaymentLinkHelper::createUnique(
    //                 paymentId: $paymentLink->payment_id,
    //                 amount: $paymentLink->amount,
    //                 dueDate: $paymentLink->due_date,
    //                 type: $paymentLink->type,
    //                 link: $paymentLink->link,
    //                 viewsCount: $paymentLink->views_count,
    //                 lastAccess: $paymentLink->last_access,
    //                 order: $paymentLink->order,
    //                 isEnabled: $paymentLink->is_enabled,
    //                 verificationCode: $paymentLink->verification_code,
    //                 collectorId: $paymentLink->collector_id,
    //                 attempt: $paymentLink->attempt,
    //             );

    //             // Use the new payment link for ELM service
    //             $paymentLink = $newPaymentLink;
    //         }

    //         $elmService = new ElmService($paymentLink);
    //         $response = $elmService->sendFirstMessage($claim);
            

    //         if ($response === null) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => "Error with Elm Service",
    //             ], 400);
    //         }

    //         // Update payment link send status after successful ELM request
    //         $paymentLink->send_status = LinkSendStatus::SENT->value;
    //         $paymentLink->save();

    //         $elmRequest = new ElmRequest;
    //         $elmRequest->batch_number = $response->BatchNumber;
    //         $elmRequest->claim_id = $paymentLink->payment->claim_id;
    //         $elmRequest->national_id = $paymentLink->payment->claim->debtor_nid;
    //         $elmRequest->link = $paymentLink->link;
    //         $elmRequest->save();
    //         $lastStatus = ClaimStatusHistory::where('claim_id', $claim->id)->latest()->first();
    //         $claim->status = ClaimStatus::TRANSFER_TO_ELM->value;
    //         $service = new ClaimService;
    //         $service->writeToClaimStatusHistory($claim->id, $lastStatus->current_status, $claim->status, auth()->user()->id);
    //         $claim->save();

    //         return response()->json([
    //             'success' => true,
    //             'message' => __('claim.status_has_been_changed_successfully'),
    //             'data' => $claim
    //         ]);
    //     } catch (\Throwable $th) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => $th->getMessage(),
    //             'data' => $claim
    //         ], 400);
    //     }
    // }

    public function transferClaimToElm(Request $request, $claimId)
    {
        $claim = Claim::find($claimId);

        try {
            $action = $this->statusActionResolver->resolve(ClaimStatus::TRANSFER_TO_ELM->value);
            $action->handle(
                claim: $claim,
                item: $request->all(),
                actingUserId: auth()->id(),
                statusFile: null,
                options: ClaimActionOptions::systemApi(),
            );
            


            return response()->json([
                'success' => true,
                'message' => __('claim.status_has_been_changed_successfully'),
                'data' => $claim
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage(),
                'data' => $claim
            ], 400);
        }
    }

    public function claimClose(Request $request, $claimId)
    {
        try {
            $claim = Claim::findOrFail($claimId);
            $action = $this->statusActionResolver->resolve(ClaimStatus::CLOSED->value);
            $result = $action->handle(
                claim: $claim,
                item: $request->all(),
                actingUserId: auth()->id(),
                statusFile: $request->file('close_file'),
                options: ClaimActionOptions::systemApi(),
            );
            

            return response()->json([
                'success' => true,
                'message' => $result == "" ? __('claim.request_has_been_sent_successfully') : __('claim.claim_has_been_closed_successfully'),

                'data' => $claim
            ]);
        
        } catch (Exception $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'data' => []
            ], 400);
        }
    }
    public function CollectedOutSystem(Request $request, $claimId)
    {
        try {
            $claim = Claim::findOrFail($claimId);

            $action = $this->statusActionResolver->resolve(ClaimStatus::COLLECTED_OUT_OF_SYSTEM->value);
            $result =  $action->handle(
                claim: $claim,
                item: $request->all(),
                actingUserId: auth()->id(),
                statusFile: $request->file('file'),
                options: ClaimActionOptions::systemApi(),
            );

            return response()->json([
                'success' => true,
                'message' => $result == "" ? __('claim.request_has_been_sent_successfully') : __('claim.claim_has_been_collected_out_of_system_successfully'),
                'data' => $claim->fresh(),
            ]);

        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function ClaimCancel(Request $request, $claimId)
    {
        try {
            $claim = Claim::find($claimId);
            try {

                $claim = Claim::findOrFail($claimId);
                $action = $this->statusActionResolver->resolve(ClaimStatus::REJECTED->value);

                $action->handle(
                    claim: $claim,
                    item: $request->all(),
                    actingUserId: auth()->id(),
                    statusFile: $request->file('cancel_file'),
                    options: ClaimActionOptions::systemApi(),
                );

                return response()->json([
                    'success' => true,
                    'message' => __('claim.claim_has_been_canceled_successfully'),
                    'data' => $claim
                ]);
            } catch (Exception $th) {
                return $this->apiErrorResponse($th->getMessage(), $claim);
            }
        } catch (Exception $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'data' => []
            ], 400);
        }
    }

    public function toOutstanding(Request $request, $claimId)
    {
        try {
            $claim = Claim::find($claimId);
            if ($claim->status !== \App\Enums\ClaimStatus::REJECTED->value) {
                throw new Exception('Claim is not rejected');
            }
            $claim->status = ClaimStatus::REGISTERED->value;
            $claim->is_close = 0;
            $claim->save();
            return response()->json([
                'success' => true,
                'message' => __('claim.claim_has_been_converted_to_outstanding'),
                'data' => $claim
            ]);
        } catch (Exception $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'data' => []
            ], 400);
        }
    }

    /**
     * Assign claim to user.
     */
    public function assign($claimId, Request $request)
    {
        $assignedBy = auth()->user();

        $request->merge([
            'claim_id' => $claimId
        ]);
        $request->validate([
            'claim_id' => 'required|exists:claims,id',
            'user_id' => 'required|exists:users,id',
        ]);

        $claimServices = new AssigningService;
        $claimServices->create([
            'claims_id' => [$request->claim_id],
            'supervisor_id' => $assignedBy->id,
            'to' => [$request->user_id],
            'from' => null,
        ]);

        $to = User::find($request->user_id);
        $claim = Claim::find($request->claim_id);

        // Check if the claim is visible to the user (who do the action) after assignment
        $claimVisibleToUser = Claim::visibleTo($assignedBy)
            ->where('id', $claim->id)
            ->exists();

        $this->notificationService->create([
            'title_ar' => 'قام المستخدم ' . $assignedBy->name . ' باسناد المطالبة للمستخدم' . $to->name . '.',
            'title_en' => ' ' . $assignedBy->name . ' assign claim to ' . $to->name,
            'description_ar' => 'قام المستخدم ' . $assignedBy->name . ' باسناد المطالبة بالرقم ' . $claim?->claim_number . ' للمستخدم ' . $to->name . ' .',
            'description_en' => 'claim number (' . $claim?->claim_number . '), has been assigned to ' . $to->name,
            'regarding' => NotificationType::CLAIM_ASSIGNED->value,
            'to' => json_encode([$to->id]),
            'user_id' => $assignedBy->id,
            'links' => json_encode([
                [
                    'label_ar' => 'عرض تفاصيل الطلب',
                    'label_en' => 'Open Claim Info',
                    'url' => 'claims/' . $claim?->id . '/info'
                ],
            ]),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Claim assigned successfully',
            'data' => [
                'claim_visable_to_user' => $claimVisibleToUser
            ]
        ]);
    }

    /**
     * Bulk Assign claim to user.
     */

    public function bulkAssignClaims(Request $request)
    {
        $request->validate([
            'claim_ids' => 'required|array|min:1',
            'claim_ids.*' => 'required|integer|exists:claims,id',
            'user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->whereNull('deleted_at')
            ]
        ]);

        try {
            $claimServices = new AssigningService;
            $claimServices->bulkCreate([
                'claims_id' => $request->claim_ids,
                'supervisor_id' => auth()->id(),
                'to' => $request->user_id,
                'from' => null,
            ]);

            $assignedTo = User::findOrFail($request->user_id);

            foreach ($request->claim_ids as $claimId) {
                $this->notificationService->create([
                    'title_ar' => 'قام المستخدم ' . auth()->user()->name . ' باسناد المطالبة للمستخدم' . $assignedTo->name . '.',
                    'title_en' => ' ' . auth()->user()->name . ' assign claim to ' . $assignedTo->name,
                    'description_ar' => 'قام المستخدم ' . auth()->user()->name . ' باسناد مطالبة لك.',
                    'description_en' => 'A claim has been assigned to you by ' . auth()->user()->name,
                    'regarding' => NotificationType::CLAIM_ASSIGNED->value,
                    'to' => json_encode([$assignedTo->id]),
                    'user_id' => auth()->id(),
                    'links' => json_encode([
                        [
                            'label_ar' => 'عرض تفاصيل الطلب',
                            'label_en' => 'Open Claim Info',
                            'url' => 'claims/' . $claimId . '/info'
                        ],
                    ]),
                ]);
            }



            return response()->json([
                "success" => true,
                'message' => 'Claims assigned successfully',
                'data' => [
                    'assigned_claims_ids' => $request->claim_ids,
                    'assigned_to' => [
                        'id' => $assignedTo->id,
                        'name' => $assignedTo->name,
                        'email' => $assignedTo->email,
                    ],
                ]
            ]);
        } catch (Exception $e) {
            return response()->json([
                "success" => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }


    public function excelBulkAssignClaims(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
        ]);

        try {
            $result = $this->assigningService->excelBulkAssign($request->file('file'));

            return response()->json([
                'success' => true,
                'message' => 'Bulk assignment completed',
                'data' => [
                    'success_count' => $result['success_count'],
                    'failed_count' => $result['failed_count'],
                    'failed_file_url' => $result['failed_file_url'] ?? null,
                    'original_file_url' => $result['original_file_url'],
                ],
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to process bulk assignment',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function excelBulkAssignClaimsOpenSpout(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls',
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

            $history = DataHistory::createDataHistory(DataHistoryEnum::ASSIGNED_CLAIMS->value, DataHistoryEnum::IMPORT->value, $uploadedFile->file_url);
            ProcessBulkAssignClaimsOpenSpout::dispatch($uploadedFile, auth()->user(), $history->ref);

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully and queued for processing',
                'data'    => [
                    'file_id'     => $uploadedFile->id,
                    'status'      => $uploadedFile->uploaded_claims_status->value,
                    'uploaded_at' => $uploadedFile->inserted_date,
                ],
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function downloadBulkAssignClaimsTemplate()
    {

        return
            Excel::download(new ClaimsBulkAssignTemplateExport(), 'claims_bulk_assign_template.xlsx', \Maatwebsite\Excel\Excel::XLSX, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
    }

    /**
     * Upload claim file.
     */
    public function uploadFile(Request $request)
    {
        $request->validate([
            'files'          => 'required|array|min:1',
            'files.*.file'   => 'required|file|max:20480|mimes:'.implode(',', \App\Enums\UploadFileType::extensionsWithoutSvg()),
            'files.*.title'  => 'nullable|string',
            'files.*.access' => ['required', new Enum(ClaimFileAccess::class)],
        ]);
    
        try {
            $filesInput = $request->file('files');
    
            $uploadedFiles = $this->filesUploader->upload(
                array_column($filesInput, 'file'),
                [
                    'source'      => FileSource::CLAIM_FILES,
                    'directory'   => 'claimFiles/' . Carbon::today()->year . '/' . $request->claimId,
                    'uploaded_by' => auth()->id(),
                    'disk'        => CloudStorageDisk::GCS_PRIVATE,
                ]
            );
    
            $filesRequest = $request->input('files');
    
            foreach ($uploadedFiles as $index => $file) {
                $fileRequest        = $filesRequest[$index];
                $accessValue = $fileRequest['access'];
    
                $this->claimService->uploadClaimFile([
                    'claim_id'   => $request->claimId,
                    'title'      => $fileRequest['title'] ?? $file->original_filename,
                    'fileAccess' => $accessValue === ClaimFileAccess::PRIVATE->value
                        ? ClaimFileAccess::PRIVATE
                        : ClaimFileAccess::PUBLIC,
                ], $file);
            }
    
            event(new UpdateClaimInstallmentEvent($request->claimId));
    
            return response()->json([
                'success' => true,
                'message' => 'Files uploaded successfully'
            ]);
    
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }


    public function updateClaimFileAccess(Request $request, $claimId, $fileId)
    {
        $request->validate([
            'access' => ['required', new Enum(ClaimFileAccess::class)],
        ]);

        try {

            $claimService = new ClaimService;
            $claimFile = $claimService->updateClaimFileVisibility($claimId, $fileId, $request->access);

            event(new UpdateClaimInstallmentEvent($claimId));

            return response()->json([
                'success' => true,
                'message' => 'File access updated successfully',
                'data' => $claimFile,
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Claim file not found',
            ], 404);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    public function deleteFile($claimId, $fileId, Request $request)
    {
        try {
            $claimFile = ClaimFile::findOrFail($fileId);

            // Delete the actual file — prefer the File model (cloud-aware) when
            // file_id is present, fall back to the legacy URL string for local-only rows.
            if ($claimFile->file) {
                $this->filesUploader->delete($claimFile->file);
            } elseif ($claimFile->url) {
                $this->filesUploader->delete($claimFile->url);
            }

            $claimFile->delete();

            return response()->json([
                'success' => true,
                'message' => 'File deleted successfully',
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'File is not found',
            ], 404);
        }
    }

    /**
     * Re-call IVR.
     */
    public function reCall($ivrId)
    {
        try {
            $ivr = Ivr::findOrFail($ivrId);
            $this->ivrService->reCall($ivr);
            return response()->json([
                'success' => true,
                'message' => 'Call reattempted successfully',
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reattempt call',
            ], 400);
        }
    }

    /**
     * Check if claim has discount request.
     */
    private function hasDiscountRequest(Claim $claim)
    {
        return StatusChangeRequest::whereClaimId($claim->id)
            ->where('to_status', ClaimStatus::DISCOUNT->value)
            ->where('request_status', RequestStatus::REGISTERED->value)
            ->count() > 0;
    }

    /**
     * Get IVRs for claim.
     */
    private function getIvrs(Claim $claim)
    {
        return $claim->payments->flatten(0)
            ->pluck('links')
            ->flatten(0)
            ->pluck('ivrs')
            ->flatten(0)
            ->sortByDesc('id')
            ->all();
    }

    /**
     * Get Maqsam IVRs for claim.
     * // Deprecated
     */
    // private function getMaqsamIvrs(Claim $claim)
    // {
    //     $ivrs = $claim->payments->flatten(0)
    //         ->pluck('links')
    //         ->flatten(0)
    //         ->pluck('maqsamIvrs')
    //         ->flatten(0)
    //         ->sortByDesc('id')
    //         ->all();

    //     if (!empty($ivrs)) {
    //         collect($ivrs)->each(function ($ivr) {
    //             $ivr->status = $this->maqsamService->getStatus([
    //                 $ivr->Event,
    //                 $ivr->FromState,
    //                 $ivr->UnreachableReason
    //             ]);
    //             $ivr->retry_number = $ivr->Trials;
    //         });
    //     }

    //     return $ivrs;
    // }

    /**
     * Handle payment toggle.
     */
    public function togglePaymentRow()
    {
        return response()->json([
            'success' => true,
            'data' => true
        ]);
    }

    /**
     * Handle payment calculations and installments.
     */
    public function calculateInstallments(Request $request)
    {
        $request->validate([
            'claim_id' => 'required|exists:claims,id',
            'number_of_installments' => 'required|numeric|max:12',
            'first_installment_date' => 'required|date',
        ]);

        $claim = Claim::findOrFail($request->claim_id);
        $payment = $claim->payments()->first();
        $remaining_amount = $payment->amount - $payment->paid_amount;
        $date = Carbon::parse($request->first_installment_date);
        $installments = [];

        for ($i = 0; $i < $request->number_of_installments; $i++) {
            $installment = [
                'order' => $i + 1,
                'date' => $date->format('Y-m-d'),
                'amount' => $i == ($request->number_of_installments - 1)
                    ? round($remaining_amount / ($request->number_of_installments - $i), 2)
                    : ceil($remaining_amount / ($request->number_of_installments - $i)),
                'edit' => false,
                'is_edited' => false,
                'status' => LinkSendStatus::NOT_SENT->value,
                'is_locked' => false,
                'id' => 0
            ];

            if ($i == $request->number_of_installments - 1) {
                $remaining_amount = 0;
                $installment['outstanding_balance'] = $remaining_amount;
            } else {
                $remaining_amount -= $installment['amount'];
                $installment['outstanding_balance'] = $remaining_amount;
            }

            $installments[] = $installment;
            $date->addMonth();
        }

        return response()->json([
            'success' => true,
            'data' => $installments
        ]);
    }

    /**
     * Handle close reasons.
     */
    public function getCloseReasons()
    {
        $closeReasons = CloseReason::all();

        return response()->json([
            'success' => true,
            'data' => $closeReasons
        ]);
    }

    /**
     * Recalculate installments.
     */
    public function recalculate(Request $request)
    {
        $request->validate([
            'claim_id' => 'required|exists:claims,id',
            'index' => 'required|integer|min:0',
            'installments' => 'required|array',
            'number_of_installments' => 'required|integer|min:1|max:12',
        ]);

        $claim = Claim::findOrFail($request->claim_id);
        $installments = $request->installments;
        $index = $request->index;

        if ($claim->payments->count() > 0) {
            $total_amount = $installments[$index]['outstanding_balance'];

            for ($i = $index + 1; $i < $request->number_of_installments; $i++) {
                if (!$installments[$i]['is_locked']) {
                    $installments[$i]['amount'] = $i - 1 == $request->number_of_installments
                        ? round($total_amount / ($request->number_of_installments - $i), 2)
                        : ceil($total_amount / ($request->number_of_installments - $i));

                    if ($installments[$i]['amount'] < 0) {
                        $installments[$i]['amount'] = 0;
                    }
                }

                if ($i == $request->number_of_installments - 1) {
                    $total_amount = 0;
                    $installments[$i]['outstanding_balance'] = $total_amount;
                } else {
                    $total_amount -= $installments[$i]['amount'];
                    $installments[$i]['outstanding_balance'] = $total_amount;
                }
            }

            return response()->json([
                'success' => true,
                'data' => $installments
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No payments found for this claim'
        ], 400);
    }

    /**
     * Calculate outstanding balances.
     */
    public function calculateOutstandingBalances(Request $request)
    {
        $request->validate([
            'claim_id' => 'required|exists:claims,id',
            'installments' => 'required|array',
            'number_of_installments' => 'required|integer|min:1|max:12',
        ]);

        $claim = Claim::findOrFail($request->claim_id);
        $installments = $request->installments;

        if ($claim->payments->count() > 0) {
            $total_amount = $claim->payments()->first()->amount;

            for ($i = 0; $i < $request->number_of_installments; $i++) {
                $total_amount -= $installments[$i]['amount'];
                $installments[$i]['outstanding_balance'] = $total_amount;
            }

            return response()->json([
                'success' => true,
                'data' => $installments
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No payments found for this claim'
        ], 400);
    }

    /**
     * Update installment amount.
     */
    public function updateInstallmentAmount(Request $request)
    {
        $request->validate([
            'claim_id' => 'required|exists:claims,id',
            'index' => 'required|integer|min:0',
            'amount' => 'required|numeric|min:0',
            'installments' => 'required|array',
            'old_installments' => 'required|array',
        ]);

        $installments = $request->installments;
        $old_installments = $request->old_installments;
        $index = $request->index;

        $old_amount = $old_installments[$index]['amount'];
        $value = $request->amount ?: 0;

        $installments[$index]['amount'] = $value;
        $installments[$index]['outstanding_balance'] = $installments[$index]['outstanding_balance']
            + ($old_amount - $value);
        $installments[$index]['edit'] = false;

        return response()->json([
            'success' => true,
            'data' => $installments
        ]);
    }

    /**
     * Update installment date.
     */
    public function updateInstallmentDate(Request $request)
    {
        $request->validate([
            'claim_id' => 'required|exists:claims,id',
            'index' => 'required|integer|min:0',
            'date' => 'required|date',
            'installments' => 'required|array',
        ]);

        $installments = $request->installments;
        $installments[$request->index]['date'] = $request->date;
        $installments[$request->index]['edit'] = false;

        return response()->json([
            'success' => true,
            'data' => $installments
        ]);
    }

    /**
     * Change amount for an installment and recalculate subsequent installments.
     *
     * @param Request $request
     * @param int $claimId
     * @param int $index
     * @return \Illuminate\Http\JsonResponse
     */
    public function changeAmount(Request $request, $claimId, $index)
    {
        $request->validate([
            'value' => 'required|numeric|min:0',
            'installments' => 'required|array',
            'old_installments' => 'required|array',
        ]);

        $claim = Claim::findOrFail($claimId);

        if ($claim->payments->count() > 0) {
            $installments = $request->installments;
            $old_installments = $request->old_installments;

            $old_amount = $old_installments[$index]['amount'];
            $value = $request->value ?: 0;

            $installments[$index]['amount'] = $value;
            $installments[$index]['outstanding_balance'] =
                $installments[$index]['outstanding_balance'] + ($old_amount - $value);
            $installments[$index]['edit'] = false;

            // Recalculate subsequent installments
            $total_amount = $installments[$index]['outstanding_balance'];
            $number_of_installments = count($installments);

            for ($i = $index + 1; $i < $number_of_installments; $i++) {
                if (!$installments[$i]['is_locked']) {
                    if ($i - 1 == $number_of_installments) {
                        $installments[$i]['amount'] = round($total_amount / ($number_of_installments - $i), 2);
                    } else {
                        $installments[$i]['amount'] = ceil($total_amount / ($number_of_installments - $i));
                    }

                    if ($installments[$i]['amount'] < 0) {
                        $installments[$i]['amount'] = 0;
                    }
                }

                if ($i == $number_of_installments - 1) {
                    $total_amount = 0;
                    $installments[$i]['outstanding_balance'] = $total_amount;
                } else {
                    $total_amount -= $installments[$i]['amount'];
                    $installments[$i]['outstanding_balance'] = $total_amount;
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Amount updated successfully',
                'data' => $installments
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No payments found for this claim'
        ], 400);
    }


    public function disputePayment(Request $request, $claimId)
    {
        $request->validate([
            'payment_links' => 'required|array',
            'payment_links.*' => 'required|exists:payment_links,id',
            'note' => 'required|min:6',
            'file' => 'nullable|file|max:20480|mimes:'.implode(',', \App\Enums\UploadFileType::extensionsWithoutSvg()),
        ]);
        $claim = Claim::findOrFail($claimId);
        if (!$claim) {
            return response()->json([
                'success' => false,
                'message' => 'You Can Not Go Back To Original Status',
            ], 422);
        }

        $lastStatus = ClaimStatusHistory::where('claim_id', $claim->id)->latest()->first();
        if (!$lastStatus) {
            return response()->json([
                'success' => false,
                'message' => 'no  status history found',
            ], 422);
        }
        $filesUploader = new FilesUploader;
        if (isset($request->file)) {
            $file_title = ClaimStatus::getString(ClaimStatus::DISPUTE_PAYMENT->value);
            $url_title = Str::slug($file_title, '_');

            $file = $filesUploader->upload($request->file, [
                'source'      => FileSource::ADDITIONAL_FILES,
                'directory'   => 'dispute_payments/' . Carbon::today()->year . '/' . $claim->id . '/' . $url_title,
                'uploaded_by' => auth()->user()->id,
            ]);
            $relativePath = $file->local_path ?? $file->cloud_path;

            $request['url'] = [$relativePath];
            $request['file_id'] = $file->id;

            $additional_file = AdditionalFile::create([
                'title'       => $file_title,
                'url'         => $relativePath,
                'file_id'     => $file->id,
                'claim_id'    => $claim->id,
                'note'        => $request->note,
                'uploaded_by' => auth()->user()->id,
                'type' => AdditionalFileType::DISPUTE_PAYMENT->value,
            ]);
        }
        $links = PaymentLink::whereIn('id', $request->payment_links)->get();
        $user = auth()->user();

        // جيب الصلاحية
        $user_perms = $user->permission->first();
        $path_perms = is_array($user_perms->claims)
            ? $user_perms->claims
            : json_decode($user_perms->claims ?? '{}', true);
        $perm_value = $path_perms['dispute_payment'] ?? null;

        // تشك على الـ structure قديمة ولا جديدة
        if (is_array($perm_value)) {
            $granted      = ($perm_value['granted'] ?? 0);
            $with_approve = ($perm_value['with_approve'] ?? 0);
        } elseif (is_numeric($perm_value)) {
            $granted      = (int) $perm_value;
            $with_approve = 0;
        } else {
            $granted      = 0;
            $with_approve = 0;
        }

         if ($granted == 1 && $with_approve == 0) {

            // update additional file 'attachment_status'
            if (isset($additional_file)) {
                $additional_file->attachment_status = FileVisibility::PUBLISHED->value;
                $additional_file->save();
            }

            foreach ($links as $link) {
                $link->send_status = LinkSendStatus::DISPUTE_PAYMENT->value;
                $link->collector_id = null;
                $link->transactions()->delete();
                $link->save();

                $payment = $link->payment;
                $payment->paid_amount = $payment->paid_amount - $link->amount > 0 ? $payment->paid_amount - $link->amount : 0;
                $payment->payment_status = PaymentStatus::PENDING->value;
                $payment->save();
            }
            if ($claim->status == ClaimStatus::PARTIAL->value) {
                $claim->status = ClaimStatus::PARTIAL->value;
            } else {
                $claim->status = $lastStatus->previous_status;
            }
            $claim->save();

            $service = new ClaimService;
            $service->writeToClaimStatusHistory($claim->id, $lastStatus->current_status, $lastStatus->previous_status, auth()->user()->id, $request);
            return response()->json([
                'success' => true,
                'message' => 'Payment Disputed Successfully',
            ], 200);
        } else {
            $status_request = StatusChangeRequest::updateOrCreate(
                [
                    'claim_id' => $claim->id,
                    'user_id' => auth()->user()->id,
                    'supervisor_id' => auth()->user()->supervisor_id,
                    'from_status' => $lastStatus->current_status,
                    'to_status' =>  LinkSendStatus::DISPUTE_PAYMENT->value,
                    'request_status' => RequestStatus::REGISTERED->value,
                ],
                [
                    'claim_id' => $claim->id,
                    'user_id' => auth()->user()->id,
                    'supervisor_id' => auth()->user()->supervisor_id,
                    'from_status' => $lastStatus->current_status,
                    'to_status' =>  LinkSendStatus::DISPUTE_PAYMENT->value,
                    'request_status' => RequestStatus::REGISTERED->value,
                    'additional_file_id' => isset($additional_file) ? $additional_file->id : null,
                    'note' => $request->note,
                ]
            );


            foreach ($links as $link) {
                PaymentRequest::create([
                    'request_id' => $status_request->id,
                    'payment_id' => $link->payment_id,
                    'link_id' => $link->id,
                    'link_amount' => $link->amount,
                    'link_order' => $link->order,
                    'link_status' => $link->send_status,
                    'link_date' => $link->due_date
                ]);
            }

            $this->notificationService->create([
                'title_ar' => __('notification.dispute_payment_request', [], 'ar'),
                'title_en' => __('notification.dispute_payment_request', [], 'en'),
                'description_ar' => __('notification.dispute_payment_request_for_claim', [], 'ar') . '(' . $claim->claim_number . ')',
                'description_en' => __('notification.dispute_payment_request_for_claim', [], 'en') . '(' . $claim->claim_number . ')',
                'regarding' => NotificationType::PERMISSION_REQUEST->value,
                'to' => json_encode([auth()->user()->supervisor_id]),
                'user_id' => auth()->id(),
                'links' => json_encode([
                    [
                        'label_ar' => 'عرض تفاصيل الطلب',
                        'label_en' => 'Open Claim Info',
                        'url' => 'requests'
                    ],
                ]),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Status Change Request Has Been Sent Successfully To Your Supervisor',
                'data' => $claim
            ]);
        }
    }
}