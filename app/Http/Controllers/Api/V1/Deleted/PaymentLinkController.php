<?php

namespace App\Http\Controllers\Api\V1\Deleted;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\CreatePaymentInstallmentRequest;
use App\Http\Requests\V1\StorePaymentLinkRequest;
use App\Http\Requests\V1\UpdateInstallmentDueDateRequest;
use App\Http\Requests\V1\UpdatePaymentLinkRequest;
use App\Http\Resources\Api\V1\PaymentLinkResource;
use App\Models\PaymentLink;
use App\Services\PaymentService;
use App\Services\SmsService;
use Exception;

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

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function pay($link)
    {

        try {

            $payment_link = PaymentLink::where('link', $link)->first();

            $payment_link->views_count += 1;

            $payment_link->last_access = now();
            $payment_link->save();

            if ($payment_link->send_status == PaymentStatus::PAID->value) {
                return response()->json([
                    'error' => true,
                    'message' => 'this link is already paid',

                ]);
            }

            $request_response = $this->payment_service->payment($payment_link);

            return $request_response;

            // return response()->json([
            // 	"error" => false,
            // 	"message" => "payed succssfully",
            // 	"data" => $request_response
            // ]);

        } catch (Exception $e) {
            dd($e->getMessage());
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
