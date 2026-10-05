<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ScheduledPaymentResource;
use App\Models\ScheduledPayment;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ScheduledPaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {}

    public function index(Request $request)
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(PaymentStatus::values())],
            'due_date_from' => 'nullable|date',
            'due_date_to' => 'nullable|date|after_or_equal:due_date_from',
            'claim_id' => 'nullable|exists:claims,id',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = ScheduledPayment::with(['claim', 'paymentTransactions']);

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (isset($validated['due_date_from'])) {
            $query->whereDate('due_date', '>=', $validated['due_date_from']);
        }

        if (isset($validated['due_date_to'])) {
            $query->whereDate('due_date', '<=', $validated['due_date_to']);
        }

        if (isset($validated['claim_id'])) {
            $query->where('claim_id', $validated['claim_id']);
        }

        $perPage = $validated['per_page'] ?? 15;
        $payments = $query->orderBy('due_date', 'asc')->paginate($perPage);

        return ScheduledPaymentResource::collection($payments);
    }

    public function processPayment($id, Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'transaction_date' => 'required|date',
            'reference_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $payment = $this->paymentService->processScheduledPayment(
                $id,
                $validated['amount'],
                $validated['transaction_date'],
                $validated['reference_number'] ?? null,
                $validated['notes'] ?? null
            );

            DB::commit();

            return new ScheduledPaymentResource($payment);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                "succes" => false,
                'message' => 'Failed to process payment: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function upcomingPayments()
    {
        $startDate = now()->startOfDay();
        $endDate = now()->addDays(7)->endOfDay();

        $payments = ScheduledPayment::with(['claim'])
            ->whereBetween('due_date', [$startDate, $endDate])
            ->where('status', PaymentStatus::PENDING->value)
            ->orderBy('due_date')
            ->limit(10)
            ->get();

        return ScheduledPaymentResource::collection($payments);
    }
}
