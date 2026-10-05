<?php

namespace App\Http\Controllers\Api\V1\Deleted;

use App\Http\Controllers\Controller;
use App\Http\Requests\Request;
use App\Http\Requests\UpdatePaymentTransactionRequest;
use App\Http\Resources\Api\V1\Collections\Collection;
use App\Http\Resources\Api\V1\PaymentResource;
use App\Models\Claim;
use App\Models\PaymentTransaction;
use App\Services\PaymentService;
use Exception;

class PaymentTransactionController extends Controller
{
    public function __construct(public PaymentService $service) {}

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
     */
    public function store(Request $request)
    {
        //
        try {

            return response()->json([
                'error' => false,
                'message' => 'create payment transaction succssfully',
                'data' => PaymentResource::make($this->service->payPayment($request)),
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
    public function show(PaymentTransaction $paymentTransaction)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PaymentTransaction $paymentTransaction)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdatePaymentTransactionRequest $request, PaymentTransaction $paymentTransaction)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PaymentTransaction $paymentTransaction)
    {
        //
    }

    public function transaction_by_claim($claim_id)
    {
        try {

            Claim::findOrFail($claim_id);

            return response()->json(

                new Collection($this->service->listTransaction($claim_id), $message = 'get all transaction belong to claim succssfully', $resourceClass = PaymentResource::class)

            );

        } catch (Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'claim with this id is not found',
            ]);
        }
    }
}
