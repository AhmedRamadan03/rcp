<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\DebtorService;
use App\Services\Payment\PaymentServiceGateway;
use Exception;
use App\Models\PaymentLink;
use App\Enums\PaymentStatus;
use App\Services\SmsService;
use App\Services\PaymentService;
use App\Http\Controllers\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Redirect;
use App\Http\Requests\V1\RedirectPaymentRequest;
use App\Http\Requests\V1\StorePaymentLinkRequest;
use App\Http\Requests\V1\UpdatePaymentLinkRequest;
use App\Http\Resources\Api\V1\PaymentLinkResource;
use App\Http\Requests\V1\CreatePaymentInstallmentRequest;
use App\Http\Requests\V1\UpdateInstallmentDueDateRequest;
use App\Models\Claim;
use App\Services\LoginAttemptService;
use Illuminate\Http\Request;

class PaymentLinkController extends Controller
{
    public function __construct(public PaymentService $service, public SmsService $payment_service) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     * StorePaymentLinkRequest
     */
    public function store(StorePaymentLinkRequest $request)
    {
        //
        try {

            return response()->json([
                'error' => false,
                'message' => 'created succssfully',
                'data' => PaymentLinkResource::make($this->service->createPaymentLink($request->validated())),
            ]);

        } catch (Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
            ]);
        }

    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePaymentLinkRequest $request)
    {
        //
        try {

            $data = $this->service->updatePaymentLink($request->validated());

            $response = [
                'error' => false,
                'message' => 'created succssfully',
                'data' => $data,
            ];

            return response()->json($response);

            // return response()->json([
            //  "error" => false,
            //  "message" => "payment link updated succssfully"
            // ]);

        } catch (Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
            ]);
        }
    }



    public function pay(Request $request,$link)
    {
        try {
            $payment_link = PaymentLink::where('link', $link)->first();

            if (!$payment_link) {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid payment link',
                ], 404);
            }

            $claim = $payment_link->payment->claim;
            
            if (! DebtorService::hasAccessToClaim($claim)) {
                throw new AuthorizationException();
            }

            // Update view count and last access
            $payment_link->increment('views_count');
            $payment_link->last_access = now();
            $payment_link->save();
            
            if ($payment_link->send_status == PaymentStatus::PAID->value) {
                return response()->json([
                    'status' => false,
                    'message' => 'This link is already paid',
                ]);
            }

            $returnlink = $request->input('client_url') ?? $payment_link->link ;
            // $returnUrl = config('app.frontend_url') . '/claim-info/' . $returnlink . '/claim';
            $returnUrl = route('debtor.callback', ['link' => $payment_link->link]);

            // Call payment service

            $payment_account = PaymentServiceGateway::getActivePaymentAccount();

            $payment_request_response = PaymentServiceGateway::chargePayment($payment_link, $payment_account, $returnUrl);

                return response()->json([
                    'status' => true,
                    'message' => 'Redirect to payment gateway',
                    'redirect_url' => $payment_request_response['payment_url']
            ]);

        } catch (Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }



    public function create_installment(CreatePaymentInstallmentRequest $request)
    {
        try {

            return response()->json([
                'error' => false,
                'message' => 'installment created succssfully',
                'data' => $this->service->createInstallments($request->validated()),
                // new Collection($this->service->createInstallments($request->validated()) , $message = "installment created succssfully" , $resourceClass=PaymentLinkResource::class)
            ]);

        } catch (Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
            ]);
        }
    }

    public function update_due_date(UpdateInstallmentDueDateRequest $request)
    {
        try {

            $this->service->updateDueDateInstallment($request->validated());

            return response()->json([
                'error' => false,
                'message' => 'due date updated succssfully',
            ]);

        } catch (Exception $e) {
            return response()->json([
                'error' => true,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
