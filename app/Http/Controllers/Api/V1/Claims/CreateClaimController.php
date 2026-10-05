<?php

namespace App\Http\Controllers\Api\V1\Claims;

use App\Enums\ClaimStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ClaimResource;
use App\Services\ClaimService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CreateClaimController extends Controller
{
    public function __construct(
        protected ClaimService $claimService
    ) {}

    public function store(Request $request)
    {
        $validated = $request->validate([
            'claim_number' => 'required|string|max:255|unique:claims,claim_number',
            'customer_name' => 'required|string|max:255',
            'customer_national_id' => 'required|string|size:10',
            'customer_phone' => 'required|string|max:20',
            'customer_email' => 'nullable|email|max:255',
            'customer_address' => 'nullable|string',
            'debt_amount' => 'required|numeric|min:0',
            'debt_date' => 'required|date',
            'description' => 'nullable|string',
            'status' => ['required', Rule::in(ClaimStatus::values())],
            'assigned_to' => 'nullable|exists:users,id',
            'external_office_id' => 'nullable|exists:external_offices,id',
            'legal_department_id' => 'nullable|exists:legal_departments,id',
        ]);

        try {
            DB::beginTransaction();

            $claim = $this->claimService->create($validated);

            // Additional logic for assignment, notifications, etc.

            DB::commit();

            return response()->json([
                "succes" => true,
                'message' => 'Claim created successfully',
                'data' => new ClaimResource($claim)
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                "succes" => false,
                'message' => 'Failed to create claim: ' . $e->getMessage(),
            ], 500);
        }
    }
}
