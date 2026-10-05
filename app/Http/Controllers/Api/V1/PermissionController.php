<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PermissionEnum;
use App\Events\General\UpdateUserEvent;
use App\Services\PermissionService;
use App\Http\Requests\V1\Settings\UpdateRequests\PermissionUpdateRequest;
use App\Http\Requests\V1\Settings\GetRequests\GetDefaultPermissionsRequest;
use Illuminate\Http\Request;
use App\Http\Resources\Api\V1\Collections\Collection;
use App\Models\GeneralPermission;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PermissionController extends BaseAPIController
{
    protected PermissionService $permissionService;

    public function __construct(PermissionService $permissionService)
    {
        $this->permissionService = $permissionService;
    }

    public function index(Request $request)
    {
        $permissions = $this->permissionService->list();
        return response()->json(
            $permissions
        );
    }

    public function getGeneralPermissions(Request $request)
    {
        $user = auth()->user();

        $permissions = safe_json_decode($user->permission->first()->permissions);
        $canShowPrivate = isset($permissions->show_private_permissions) && $permissions->show_private_permissions == 1;

        $query = GeneralPermission::where('type', PermissionEnum::PUBLIC->value);

        if ($canShowPrivate) {
            $query->orWhere('type', PermissionEnum::PRIVATE->value);
        }

        return response()->json(
            $query->get(['permission', 'path', 'type','permission_mode'])->groupBy('path')
        );
    }


    /**
     * Update or create permissions for a user
     */
    public function update(PermissionUpdateRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $formattedData = $request->getFormattedData();

            $permission = $this->permissionService->create($formattedData);
            event(new UpdateUserEvent());
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Permissions updated successfully',
                'data' => $permission->toArray()
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update permissions: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }


    /**
     * Get default permissions for a role
     */
    public function getDefaultPermissions(GetDefaultPermissionsRequest $request): JsonResponse
    {
        try {
            $roleId = $request->validated()['roleId'];
            $defaultPermissions = default_permission($roleId);

            $decodedPermissions = [];
            foreach ($defaultPermissions as $category => $value) {
                if (is_string($value) && json_decode($value) !== null) {
                    $decodedPermissions[$category] = json_decode($value, true);
                } else {
                    $decodedPermissions[$category] = $value;
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Default permissions retrieved successfully',
                'data' => [
                    'role_id' => (int) $roleId,
                    'permissions' => $decodedPermissions
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve default permissions: ' . $e->getMessage(),
                'data' => null
            ], 500);
        }
    }
}
