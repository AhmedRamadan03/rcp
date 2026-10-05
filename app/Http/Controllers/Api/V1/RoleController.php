<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\DataHistoryEnum;
use App\Http\Requests\V1\ExportRoleRequest;
use App\Http\Requests\V1\Settings\CreateRequests\RoleStoreRequest;
use App\Http\Requests\V1\Settings\UpdateRequests\RoleUpdateRequest;
use App\Models\Role;
use App\Services\RoleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use App\Http\Resources\Api\V1\Collections\Collection;
use App\Http\Resources\Api\V1\RoleResource;
use App\Models\DataHistory;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
class RoleController extends BaseAPIController
{
    public function index(ExportRoleRequest $request)
    {
        $perPage = $request->input('per_page', 10);
        $roles = Role::filter($request->filters())->paginate($perPage);
        return response()->json(
            new Collection($roles, 'Roles retrieved successfully', JsonResource::class)
        );
    }
    public function getAllRoles(Request $request)
    {
        $roles = Role::get();
        return $this->successResponse(RoleResource::collection($roles), 'Roles retrieved successfully');
            
        
    }

    public function openSpoutExportRoles(ExportRoleRequest $request)
    {
        $fileName = 'roles_export_' . time() . '.xlsx';
        
        $filters = $request->filters();
        $history = DataHistory::createDataHistory(DataHistoryEnum::EXPORT_ROLES->value, DataHistoryEnum::EXPORT->value, '',$filters);
        \App\Jobs\ExportRolesOpenSpout::dispatch(user:auth()->user(), fileName: $fileName, historyRef: $history->ref, columns: $request->columns() ?? [], filters: $filters);

       return $this->successResponse('Your file is being prepared. You will be notified once it is ready.');

    }

    public function show($id)
    {
        $role = Role::find($id);

        if (is_null($role)) {
            return $this->errorResponse('Role not found', 404);
        }

        return $this->successResponse($role);
    }

    public function store(RoleStoreRequest $request)
    {
        try {
            $role = (new RoleService())->create($request->validated());

            return $this->successResponse($role, 'Role created successfully', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create role.' . $e->getMessage(), 422, []);
        }
    }


    public function update(RoleUpdateRequest $request, $id)
    {

        try {
            $role = Role::find($id);

            if (is_null($role)) {
                return $this->errorResponse('Role not found', 404);
            }

            $response = (new RoleService)->update($role, $request->validated());

            return $this->successResponse($response, 'Role updated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update role', 500, []);
        }
    }

    public function destroy($id, Request $request)
    {
        try {
            $role = Role::findOrFail($id);

            $has_users_assigned = $role->users()->exists();

            $request->validate([
                'new_role_id' => [
                    Rule::requiredIf($has_users_assigned),
                    'exists:roles,id',
                    Rule::notIn($id),
                ],
            ]);

            DB::beginTransaction();

            if ($has_users_assigned) {
                (new RoleService())->transferUsersToNewRole($role->id, $request->new_role_id);
            }

            (new RoleService())->delete($role);

            DB::commit();

            return $this->successResponse(null, 'Role deleted successfully');

        } catch (ModelNotFoundException $e) {
            return response()->json([
                "succes" => false,
                'message' => 'Role not found to be deleted',
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                "succes" => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
