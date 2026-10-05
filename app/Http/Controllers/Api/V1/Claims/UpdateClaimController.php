<?php

namespace App\Http\Controllers\Api\V1\Claims;

use App\Enums\ClaimStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ClaimResource;
use App\Models\Claim;
use App\Services\ClaimService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UpdateClaimController extends Controller
{
    public function __construct(
        protected ClaimService $claimService
    ) {}

    public function update($id, Request $request)
    {
        $claim = Claim::findOrFail($id);

        $validated = $request->validate([
            'customer_name' => 'sometimes|required|string|max:255',
            'customer_national_id' => ['sometimes', 'required', 'string', 'size:10',
                Rule::unique('claims')->ignore($claim->id)],
            'customer_phone' => 'sometimes|required|string|max:20',
            'customer_email' => 'nullable|email|max:255',
            'customer_address' => 'nullable|string',
            'debt_amount' => 'sometimes|required|numeric|min:0',
            'debt_date' => 'sometimes|required|date',
            'description' => 'nullable|string',
            'status' => ['sometimes', 'required', Rule::in(ClaimStatus::values())],
            'assigned_to' => 'nullable|exists:users,id',
            'external_office_id' => 'nullable|exists:external_offices,id',
            'legal_department_id' => 'nullable|exists:legal_departments,id',
            'close_reason_id' => 'nullable|exists:close_reasons,id',
            'close_notes' => 'nullable|required_with:close_reason_id|string',
        ]);

        try {
            DB::beginTransaction();

            $claim = $this->claimService->update($claim, $validated);

            // Handle status changes, notifications, etc.
            if (isset($validated['status'])) {
                $this->handleStatusChange($claim, $validated);
            }

            DB::commit();

            return response()->json([
                "succes" => true,
                'message' => 'Claim updated successfully',
                'data' => new ClaimResource($claim)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                "succes" => false,
                'message' => 'Failed to update claim: ' . $e->getMessage(),
            ], 500);
        }
    }

    protected function handleStatusChange(Claim $claim, array $data)
    {
        $status = $data['status'];

        switch ($status) {
            case ClaimStatus::CLOSED->value:
                $this->claimService->closeClaim(
                    $claim,
                    $data['close_reason_id'] ?? null,
                    $data['close_notes'] ?? null
                );
                break;

            case ClaimStatus::IN_PROGRESS->value:
                $this->claimService->markInProgress($claim);
                break;

            // Add other status change handlers as needed
        }
    }
}
