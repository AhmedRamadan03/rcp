<?php

namespace App\Http\Controllers\Api\V1\Settings;


use App\Services\Settings\ThirdPartyService;
use App\Http\Resources\Api\V1\Settings\ThirdPartyResource;
use App\Http\Requests\V1\Settings\CreateRequests\ThirdPartyStoreRequest;
use App\Http\Requests\V1\Settings\UpdateRequests\ThirdPartyUpdateRequest;
use App\Models\ThirdParty;
use App\Enums\ThirdPartyType;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;


class ThirdPartyController extends Controller
{
    use ApiResponse;

    protected ThirdPartyService $thirdPartyService;

    public function __construct(ThirdPartyService $thirdPartyService)
    {
        $this->thirdPartyService = $thirdPartyService;
    }

    /**
     * Get available providers for a type
     * GET /api/third-parties/providers?provider_type=sms
     */
    public function providers(Request $request): JsonResponse
    {
        $request->validate([
            'provider_type' => 'required|string|in:' . implode(',', array_column(ThirdPartyType::cases(), 'value'))
        ]);

        $provider_type = ThirdPartyType::from($request->provider_type);

        $providers = $this->thirdPartyService->getProvidersByType($provider_type);
        return response()->json([
            'success' => true,
            'message' => "Third parties providers retrieved successfully",
            'data' => $providers
        ]);
    }

    public function store(ThirdPartyStoreRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['client_id'] = auth()->user()->client_id;

        try {
            $thirdParty = $this->thirdPartyService->storeThirdParty($validated);

            return response()->json([
                'success' => true,
                'message' => "Third party provider created successfully",
                'data' => new ThirdPartyResource($thirdParty)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Failed to create third party provider",
                'error' => $e->getMessage()
            ], 422);
        }
    }

    public function update(ThirdPartyUpdateRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();
            $thirdParty = ThirdParty::findOrFail($validated['id']);

            $thirdParty = $this->thirdPartyService->updateThirdParty($thirdParty, $validated);

            return response()->json([
                'success' => true,
                'message' => "Third party provider updated successfully",
                'data' => new ThirdPartyResource($thirdParty)
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => "Third party provider not found",
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Failed to update third party provider",
                'error' => $e->getMessage()
            ], 422);
        }
    }


    public function destroy(int $id): JsonResponse
    {
        try {
            $thirdParty = ThirdParty::where('id', $id)
                ->where('client_id', auth()->user()->client_id)
                ->firstOrFail();

            $this->thirdPartyService->deleteThirdParty($thirdParty);

            return response()->json([
                'success' => true,
                'message' => "Third party provider deleted successfully"
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => "Third party provider not found",
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Set a third-party as default (active) for its type
     * POST /api/settings/third-parties/{id}/default
     */
    public function setDefault(int $id): JsonResponse
    {
        try {
            $thirdParty = ThirdParty::where('id', $id)
                ->where('client_id', auth()->user()->client_id)
                ->firstOrFail();

            $thirdParty = $this->thirdPartyService->setDefaultThirdParty($thirdParty);

            return response()->json([
                'success' => true,
                'message' => "Third party provider set as default successfully",
                'data' => new ThirdPartyResource($thirdParty)
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => "Third party provider not found",
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Failed to set third party provider as default",
            ], 400);
        }
    }


    /**
     * Toggle a third-party between active and inactive
     * POST /api/settings/third-parties/{id}/toggle
     */
    public function toggle(int $id): JsonResponse
    {
        try {
            $thirdParty = ThirdParty::where('id', $id)
                ->where('client_id', auth()->user()->client_id)
                ->firstOrFail();

            $thirdParty = $this->thirdPartyService->toggleThirdPartyActive($thirdParty);
            $status = $thirdParty->is_active ? 'activated' : 'deactivated';
            return response()->json([
                'success' => true,
                'message' => "Third party provider {$status} successfully",
                'data' => new ThirdPartyResource($thirdParty)
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => "Third party provider not found",
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }


}
