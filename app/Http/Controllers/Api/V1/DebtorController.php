<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\TransactionStatusEnquiryAction;
use App\Enums\AdditionalFileType;
use App\Enums\ClaimStatus;
use App\Enums\MessageTemplates;
use App\Enums\PaymentType;
use App\Enums\ThirdPartyType;
use App\Http\Resources\Api\V1\Dashboard\ClientInfoResource;
use App\Models\ThirdParty;
use App\Services\ClaimService;
use App\Services\DebtorService;
use App\Services\MessageService;
use App\Services\OtpService;
use App\Services\Payment\PaymentServiceGateway;
use App\Services\SmsService;
use Exception;
use App\Models\Claim;
use App\Enums\RejectType;
use App\Enums\RejectStatus;
use App\Models\BankAccount;
use App\Models\PaymentLink;
use App\Enums\LinkSendStatus;
use App\Enums\NotificationType;
use App\Models\RejectedClaim;
use App\Models\VerificationCode;
use App\Services\PaymentService;
use App\Models\PaymentTransaction;
use App\Services\RejectionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Log;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\Browsershot\Browsershot;
use App\Http\Controllers\Controller;
use App\Services\LoginAttemptService;
use App\Enums\PaymentTransactionStatus;
use App\Events\ClaimInfo\UpdateClaimInstallmentEvent;
use App\Events\General\UpdateRequestEvent;
use App\Http\Requests\V1\CreateRejectionRequest;

use App\Http\Requests\V1\RedirectPaymentRequest;
use App\Http\Resources\Api\V1\RejectionResource;
use App\Http\Requests\V1\VerificationCodeRequest;
use App\Models\AdditionalFile;
use App\Models\Message;
use App\Models\Payment;
use App\Models\Setting;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class DebtorController extends Controller
{

    public function __construct(
        public RejectionService $service
    ) {}

    public function requestCode($link)
    {
        
        $payment_link = PaymentLink::with('payment.claim')->where('link', $link)->first();
        
        
        if (!$payment_link) {
            return response()->json([
                'success' => false,
                'message' => 'Payment link not found'
            ], 404);
        }
       


        return response()->json([
            'success' => true,
            'need_verification_code' => true,
            'message' => 'Please submit verification code'
        ]);
    }


    public function callback($link)
    {
        
        $payment_link = PaymentLink::with('payment.claim')->where('link', $link)->first();
        
        
        if (!$payment_link) {
            return response()->json([
                'success' => false,
                'message' => 'Payment link not found'
            ], 404);
        }
        // معالجة الدفع لو في PaymentId
        if (request()->charge_id ) {
            $chargeId = request()->charge_id;
            // الدفع لسه متسددش → نعالج الدفع
            if ($payment_link->send_status != LinkSendStatus::PAID->value) {
                // (new PaymentService)->payPayment(request());
                (new TransactionStatusEnquiryAction($chargeId))->execute();
            }
            $baseLink = $payment_link->payment?->link ?? $payment_link->payment?->claim?->firstLinkWithoutStatus->link;

            return redirect()->away(config('app.frontend_url').'/claim-info/' .  $baseLink . '/claim?status=' . request()->status);
        }


        return response()->json([
            'success' => true,
            'need_verification_code' => true,
            'message' => 'Please submit verification code'
        ]);
    }

    public function fetchData($link,$type)
    {
        if($type == 'payment'){
            $payment = Payment::where('link', $link)->first();
        }else{
            $payment = PaymentLink::where('link', $link)->first();
        }
        
        if (!$payment ) {        // شرط واحد نهائي يجمع كل حالات الفشل
            return response()->json([
                'success' => false,
                'message' => 'Payment not found'
            ], 404);
        }

        $realPayment = $payment instanceof PaymentLink ? $payment->payment : $payment;

        return $this->getData($link, $realPayment);
    }



    public function verifyCode(VerificationCodeRequest $request, $link)
    {
        $payment = Payment::where('link', $link)->first();
        $paymentLink = PaymentLink::where('link', $link)->first();

        if (! $payment && ! $paymentLink) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found',
            ], 404);
        }

        $loginAttemptService = new LoginAttemptService();

        // نفس منطق الفحص المستخدم في redirectPayment
        if ($response = $loginAttemptService->checkIpLockout($request->ip())) {
            return $response;
        }

        $resolvedClaim = $payment
            ? $payment->claim
            : $paymentLink->payment->claim;

        $request->merge([
            'claim_id' => $resolvedClaim->id,
            'claim_number' => $resolvedClaim->claim_number,
            'debtor_nid' => $resolvedClaim->debtor_nid,
            'phone' => $resolvedClaim->debtor_phone,
        ]);

        $record = null;

        if ($payment) {
            $claimForPayment = $payment->claim;

            if ($this->codeMatches($payment, $claimForPayment, $request)) {
                $record = $payment;
            }
        }

        if (! $record && $paymentLink) {
            $claimForPaymentLink = $paymentLink->payment->claim;

            if ($this->codeMatches($paymentLink, $claimForPaymentLink, $request)) {
                $record = $paymentLink;
            }
        }

        if (! $record) {
            $loginAttemptService->addFailedLoginAttempt($request);

            return response()->json([
                'success' => false,
                'message' => 'Invalid verification code',
            ], 400);
        }

        $loginAttemptService->addSucceededLoginAttempt($request);

        return $this->successfulClientLoginResponse($record, $link);
    }
    /**
     * Checks whether the request's verification_code matches the given
     * payment/payment-link record — including the ART-client fallback rule
     * (trailing digits after the last '-' in the claim number).
     */
    private function codeMatches($record, $claim, VerificationCodeRequest $request): bool
    {
        if (hash_equals((string) $record->verification_code, (string) $request->verification_code)) {
            return true;
        }

        if (config('app.client_name') === \App\Enums\Clients::ART->value) {
            $claimNumberSuffix = substr($claim->claim_number, strrpos($claim->claim_number, '-') + 1);

            return hash_equals((string) $claimNumberSuffix, (string) $request->verification_code);
        }

        return false;
}

    private function successfulClientLoginResponse(Payment|PaymentLink $record, string $link)
    {
        $token = DebtorService::issueAccessToken($record);
        $isProduction = app()->environment('production');

        return response()->json([
            'success' => true,
            'message' => 'Verification code verified successfully',
            'data' => [
                'link' => $link,
                'type' => $record instanceof PaymentLink ? 'payment_link' : 'payment',
            ],
        ])->cookie(
            'client_access_token',
            $token['access_token'],
            config('app.client_view.ttl_minutes'),
            '/',
            $isProduction ? config('app.session_domain') : null,
            true,
            true,
            false,
            $isProduction ? 'Lax' : 'None'
        );
    }
    

    public function clientViewLogout()
    {
        
        DebtorService::revokeCurrentAccessToken();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    }

  private function getData($link, Payment $payment)
{
    // تحديث عدد الزيارات وآخر وصول
    $lastMsg = Message::where('claim_id', $payment->claim_id)
        ->whereNotNull('payment_link_id')
        ->latest()
        ->first();

    if ($lastMsg) {
        PaymentLink::where('id', $lastMsg->payment_link_id)
            ->increment('views_count', 1, [
                'last_access' => now()
            ]);
    }

    // جلب البيانات المرتبطة بالـ claim - باستخدام نفس الـ Payment المُمرر
    $payment->load([
        'claim.createdBy',
        'claim.client',
        'claim.claimFiles',
        'claim.rejections',
        'claim.discharge',
    ]);

    $canReject = RejectedClaim::where('claim_id', $payment->claim->id)
        ->where('type', RejectType::USER->value)
        ->doesntExist();

    $installments = PaymentLink::with('voucher')
        ->where('payment_id', $payment->id)
        ->where('send_status', '!=', LinkSendStatus::CANCELED->value)
        ->orderBy('order', 'asc')
        ->get();

    $transactions = PaymentTransaction::where('payment_id', $payment->id)
        ->where('send_status', '!=', PaymentTransactionStatus::CREATED->value)
        ->orderBy('created_at', 'desc')
        ->get();

    $full_payment_link = PaymentLink::where('payment_id', $payment->id)
        ->where('type', PaymentType::FULL_PAYMENT->value)
        ->value('link');
    $hasActivePaymentService = false;

    try {
        PaymentServiceGateway::getActivePaymentAccount();
        $hasActivePaymentService = true;
    } catch (Exception $e) {
        // no active/synced payment account
    }

    return response()->json([
        'success' => true,
        'client_info' => new ClientInfoResource($this),
        'has_active_payment_service' => $hasActivePaymentService,
        'link' => $link,
        'full_payment_link' => $full_payment_link,   // 👈 لاحظ: كان فيه bug هنا كمان (شرح تحت)
        'claim' => $payment->toArray(true),
        'canReject' => $canReject,
        'installments' => $installments,
        'transactions' => $transactions,
        'clientRequests' => RejectionResource::collection(
            $payment->claim?->rejections?->load('attachments') ?? collect()
        ),
        'hide_phone_number'=>Setting::hidePhoneNumber()->value
        
    ]);
}


    /**
     * Store a newly created resource in storage.
     */
    public function createRequest(CreateRejectionRequest $request)
    {
        try {
            $inputs = $request->validated();

            $claim = Claim::find($inputs['claim_id']);
            
            if (! DebtorService::hasAccessToClaim($claim)) {
                throw new AuthorizationException();
            }

            $this->service->createDebtorRejection($request->validated());
            $data = RejectedClaim::with('attachments')->where('claim_id', $inputs['claim_id'])->get();
            return response()->json([
                'status' => true,
                'message' => 'created succssfully',
            ]);
        } catch (Exception $exception) {
            return response()->json([
                'status' => false,
                'message' => $exception->getMessage(),
            ]);
        }
    }


    public function sendUpdatePhoneOtp(Request $request, $id)
    {
        try {
            $claim = Claim::find($id);

            if (! $claim) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Claim not found',
                ],404);
            }

            if (! DebtorService::hasAccessToClaim($claim)) {
                throw new AuthorizationException();
            }

            $request->validate([
                'phone' => ['required', 'phone:INTERNATIONAL'],
            ], [
                'phone.required' => 'Phone number is required',
                'phone.phone' => 'Please enter a valid phone number including the country code ',
            ]);

            // Rate limit: 1 attempt per 30 seconds
            $rateLimitKey = 'phone_update_otp:' . $id . '|' . $request->ip();
            
            if (RateLimiter::tooManyAttempts($rateLimitKey, 1)) {
                $seconds = RateLimiter::availableIn($rateLimitKey);
                return response()->json([
                    'status' => false,
                    'message' => "Please wait {$seconds} seconds before requesting a new OTP",
                ], 429);
            }

            RateLimiter::hit($rateLimitKey, 30);

            $code = (new OtpService())->generate($claim, 'phone_update', $request->phone);

            try {
                $messageService = new MessageService;
                $message = $messageService->convertTemplate(MessageTemplates::ELM_OTP->value, $code->id);


                (new SmsService())->sendSms(
                   $request->phone,
                   $message,
                   $claim->id,
                   MessageTemplates::ELM_OTP->value,
                   template_model_id: $code->id
                );
            } catch (Exception $e) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Failed to send OTP' . $e->getMessage(),
                ],400);
            }

            return response()->json([
                'status'     => true,
                'message'    => 'OTP sent successfully',
                'expires_in' => OtpService::TTL_MINUTES . ' minutes',
            ]);

        } catch (Exception $exception) {
            return response()->json([
                'status'  => false,
                'message' => $exception->getMessage(),
            ],400);
        }
    }

    public function verifyUpdatePhoneOtp(Request $request, $id)
    {
        try {
            $claim = Claim::find($id);

            if (! $claim) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Claim not found',
                ],404);
            }

            if (! DebtorService::hasAccessToClaim($claim)) {
                throw new AuthorizationException();
            }

            $request->validate([
                'phone' => ['required', 'phone:INTERNATIONAL'],
                'otp' => ['required', 'string'],
            ], [
                'phone.required' => 'Phone number is required',
                'phone.phone' => 'Please enter a valid phone number including the country code ',
                'otp.required' => 'OTP is required',
            ]);

            $verified_code = (new OtpService())->verify($claim, 'phone_update', $request->otp, $request->phone);

            if (! $verified_code) {
                return response()->json([
                    'status'  => false,
                    'message' => 'Invalid or expired OTP',
                ],400);
            }


            $claim->debtor_phone = $verified_code->phone;

            if ($claim->status == ClaimStatus::TRANSFER_TO_ELM->value) {
                $claim->status = ClaimStatus::APPROVED->value;
                $user_id = $claim->assignee?->to ?? $claim->user_id;

                (new ClaimService())->writeToClaimStatusHistory(
                    $claim->id,
                    previous_status: ClaimStatus::TRANSFER_TO_ELM->value,
                    current_status: ClaimStatus::APPROVED->value,
                    user_id: $user_id
                );
            }

            $claim->save();

            NotificationService::create([
                'title_ar'       => __('notification.update_debtor_phone', [], 'ar'),
                'title_en'       => __('notification.update_debtor_phone', [], 'en'),
                'description_ar' => __('notification.update_debtor_phone_for_claim', [], 'ar') . '( ' . $claim->claim_number . ' )  ',
                'description_en' => __('notification.update_debtor_phone_for_claim', [], 'en') . '( ' . $claim->claim_number . ' )  ',
                'regarding'      => NotificationType::GENERAL->value,
                'user_id'        => $claim->assignee->to,
                'to'             => json_encode([$claim->assignee->to]),
                'links'          => json_encode([[
                    'label_ar' => 'الذهاب للمطالبة',
                    'label_en' => 'Go to claim',
                    'url'      => 'claims/' . $claim->id . '/info',
                ]]),
            ]);

            event(new UpdateClaimInstallmentEvent($claim->id));

            return response()->json([
                'status'  => true,
                'message' => 'Debtor phone updated successfully',
            ]);

        } catch (Exception $exception) {
            return response()->json([
                'status'  => false,
                'message' => $exception->getMessage(),
            ],400);
        }
    }


    public function cancelRequest($id)
    {
        try {
            $rejectionExist = RejectedClaim::where('id', $id)->first();

            if (! DebtorService::hasAccessToClaim($rejectionExist->claim)) {
                throw new AuthorizationException();
            }

            if ($rejectionExist->status != RejectStatus::NOT_SEEN->value) {
                return response()->json([
                    'status' => false,
                    'message' => 'Request already .' . $rejectionExist->status,
                ]);
            }

            $rejectionExist->status = RejectStatus::CANCELLED->value;
            $rejectionExist->save();
            $data = RejectedClaim::with('attachments')->where('claim_id', $rejectionExist->claim_id)->get();
             event(new UpdateRequestEvent());
            return response()->json([
                'status' => true,
                'message' => 'Cancelled succssfully',
                'data' => RejectionResource::collection($data),
            ]);
        } catch (Exception $exception) {
            return response()->json([
                'status' => false,
                'message' => $exception->getMessage(),
            ]);
        }
    }


    public function redirectPayment(RedirectPaymentRequest $request)
    {
        $loginAttemptService = new LoginAttemptService();

        if ($response = $loginAttemptService->checkIpLockout($request->ip())) {
            return $response;
        }
        try {
            $record = Payment::where('verification_code', $request->verification_code)
                ->whereHas('claim', function ($query) use ($request) {
                    $query->where('debtor_nid', $request->debtor_nid);
                })
                ->first();

            if (! $record) {
                $record = PaymentLink::where('verification_code', $request->verification_code)
                    ->whereHas('payment.claim', function ($query) use ($request) {
                        $query->where('debtor_nid', $request->debtor_nid);
                    })
                    ->first();
            }

            if (! $record) {
                (new LoginAttemptService)->addFailedLoginAttempt($request);

                return response()->json([
                    'status' => false,
                    'message' => __('redirect-payment.user_not_found'),
                ], 422);
            }

            $claim = $record instanceof PaymentLink
                ? $record->payment->claim
                : $record->claim;

            $request->merge([
                'claim_id'     => $claim->id,
                'claim_number' => $claim->claim_number,
                'debtor_nid'   => $claim->debtor_nid,
                'phone'        => $claim->debtor_phone,
            ]);

            (new LoginAttemptService)->addSucceededLoginAttempt($request);

            return $this->successfulClientLoginResponse($record, $record->link);
        } catch (Exception $e) {
            (new LoginAttemptService)->addFailedLoginAttempt($request);

            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function obligationLetter($link)
    {
        try {

            app()->setLocale('ar');

            $payment_link = PaymentLink::where('link', $link)->first();
            
            if (!$payment_link) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid Full Payment Link'
                ], 404);
            }
            
            $claim = $payment_link->payment->claim;
            
            if (! DebtorService::hasAccessToClaim($claim)) {
                throw new AuthorizationException();
            }

            $setting = Setting::proofOfDebt()->value;
            $view = $setting == 1 && $payment_link->payment?->claim?->proof_debt_enable ? 'public' : 'private';
            if ($view == 'private') {
                return response()->json([
                    'status' => false,
                    'message' => 'Proof of debt is not enabled for this claim',
                ], 403);
            }


            // For API: handle payment callback logic if needed
            // Deprecated
            // if (request()->query('ResponseCode') && ($payment_link->send_status != LinkSendStatus::PAID->value)) {
            //     $service = new PaymentService;
            //     $service->payPayment(request());
            // }

            $claim = $payment_link->payment->claim;

             $additional_file = AdditionalFile::where('claim_id', $claim->id)
                ->where('type', AdditionalFileType::VOUCHER->value)
                ->first();

            return response()->json([
                'status' => true,
                'message' => 'Voucher generated successfully',
                'voucher_url' => route('download', ['key' => $additional_file->file?->uuid ?? $additional_file->url]),
            ], 200);
        } catch (\Throwable $e) {

            // لو عايز تخزن الخطأ في اللوج
            Log::error('Voucher Generation Failed: ' . $e->getMessage());

            return response()->json([
                'status'  => false,
                'message' => 'Failed to generate voucher',
                'error'   => $e->getMessage(), // لو مش عايز تبعت الرسالة احذف السطر دا
            ], 500);
        }
    }

    public function getBankInfo()
    {
        return response()->json([
            'status' => true,
            'message' => 'Bank info fetched successfully',
            'data' => BankAccount::active()->first(),
        ], 200);
    }
}