<?php

namespace App\Http\Helpers;

use App\Enums\Roles;
use App\Models\Role;
use App\Models\User;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;

class ApiHelper
{
    /**
     * Get all roles with their permissions
     *
     * @return array
     */
    public static function getRoles(): array
    {
//        return Role::with(['permissions' => function($query) {
//            $query->select('id', 'name', 'description');
//        }])->get(['id', 'name', 'description'])->toArray();

        return Role::all()->toArray();
    }

    /**
     * Get a specific role with its permissions
     *
     * @param int $id
     * @return array|null
     */
    public static function getRole(int $id): ?array
    {
        return Role::with(['permissions' => function($query) {
            $query->select('id', 'name', 'description');
        }])->findOrFail($id, ['id', 'name', 'description'])->toArray();
    }

    /**
     * Create a new role
     *
     * @param array $data
     * @return array
     */
    public static function createRole(array $data): array
    {
        DB::beginTransaction();

        try {
            $role = Role::create([
                'name' => $data['name'],
                'description' => $data['description'] ?? null
            ]);

            if (isset($data['permissions'])) {
                $role->permissions()->sync($data['permissions']);
            }

            DB::commit();
            return $role->toArray();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update an existing role
     *
     * @param int $id
     * @param array $data
     * @return array
     */
    public static function updateRole(int $id, array $data): array
    {
        $role = Role::findOrFail($id);

        DB::beginTransaction();
        try {
            $role->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? $role->description
            ]);

            if (isset($data['permissions'])) {
                $role->permissions()->sync($data['permissions']);
            }

            DB::commit();
            return $role->toArray();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Delete a role
     *
     * @param int $id
     * @return void
     */
    public static function deleteRole(int $id): void
    {
        $role = Role::findOrFail($id);

        DB::beginTransaction();
        try {
            // First detach all users from this role
            $role->users()->detach();

            // Then delete the role
            $role->delete();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Assign a role to a user
     *
     * @param int $userId
     * @param int $roleId
     * @return array
     */
    public static function assignRoleToUser(int $userId, int $roleId): array
    {
        $user = User::findOrFail($userId);

        try {
            $user->roles()->attach($roleId);
            return $user->toArray();
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Remove a role from a user
     *
     * @param int $userId
     * @param int $roleId
     * @return array
     */
    public static function removeRoleFromUser(int $userId, int $roleId): array
    {
        $user = User::findOrFail($userId);

        try {
            $user->roles()->detach($roleId);
            return $user->toArray();
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Get all permissions
     *
     * @return array
     */
    public static function getPermissions(): array
    {
        return Permission::select('id', 'name', 'description')->get()->toArray();
    }

    public static function isCollectorOrExternal(): bool
    {
        $roleId = auth()->user()->role_id;
        return in_array($roleId, [Roles::COLLECTOR->value, Roles::EXTERNAL_OFFICE->value]);
    }

    public static function applyAssigneeOrDelegationFilter($query, $userIds, string $relationPath): void
    {
        $query->orWhereHas($relationPath, function ($q) use ($userIds) {
            $q->whereIn('to', $userIds)->where('is_active', 1);
        })->orWhereHas($relationPath, function ($q) use ($userIds) {
            $q->where('is_active', 1)
                ->whereHas('toUser.delegationsFrom', function ($q) use ($userIds) {
                    $q->where('to', $userIds)->where('is_active', 1);
                });
        });
    }
}
