<?php

namespace App\Services;

use App\Models\GeneralPermission;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

class RoleService
{
    public function list()
    {
        return Role::all();
    }

   public function create($role): Role
    {
        $permissions = [];
        $general_permissions = GeneralPermission::select('permission as key', 'path', 'permission_mode')
            ->get()
            ->groupBy('path')
            ->toArray();

        foreach ($general_permissions as $category_key => $category) {
            $permissions[$category_key] = [];

            foreach ($category as $permission) {
                $modes = is_array($permission['permission_mode'])
                    ? $permission['permission_mode']
                    : json_decode($permission['permission_mode'] ?? '[]', true) ?? [];

                if (isset($role['default_permissions'][$category_key][$permission['key']])) {
                    $value = $role['default_permissions'][$category_key][$permission['key']];

                    if (is_array($value)) {
                        $entry = ['granted' => (int) ($value['granted'] ?? 0)];
                        foreach ($modes as $mode) {
                            $entry[$mode] = (int) ($value[$mode] ?? 0);
                        }
                    } else {
                        $entry = ['granted' => (int) $value];
                        foreach ($modes as $mode) {
                            $entry[$mode] = 0;
                        }
                    }
                } else {
                    $entry = ['granted' => 0];
                    foreach ($modes as $mode) {
                        $entry[$mode] = 0;
                    }
                }

                $permissions[$category_key][$permission['key']] = $entry;
            }

            $permissions[$category_key] = json_encode($permissions[$category_key]);
        }

        $role['default_permissions'] = json_encode($permissions);

        return Role::create($role)->refresh();
    }

    public function update(Role $role, $request): Role
    {
        $permissions = [];
        $general_permissions = GeneralPermission::select('permission as key', 'path', 'permission_mode')
            ->get()
            ->groupBy('path')
            ->toArray();

        foreach ($general_permissions as $category_key => $category) {
            $permissions[$category_key] = [];

            foreach ($category as $permission) {
                $modes = is_array($permission['permission_mode'])
                    ? $permission['permission_mode']
                    : json_decode($permission['permission_mode'] ?? '[]', true) ?? [];

                if (isset($request['default_permissions'][$category_key][$permission['key']])) {
                    $value = $request['default_permissions'][$category_key][$permission['key']];

                    if (is_array($value)) {
                        // جديدة — تأكد إن كل الـ modes موجودة
                        $entry = ['granted' => (int) ($value['granted'] ?? 0)];
                        foreach ($modes as $mode) {
                            $entry[$mode] = (int) ($value[$mode] ?? 0);
                        }
                        $permissions[$category_key][$permission['key']] = $entry;
                    } else {
                        // قديمة = 0 أو 1
                        $entry = ['granted' => (int) $value];
                        foreach ($modes as $mode) {
                            $entry[$mode] = 0;
                        }
                        $permissions[$category_key][$permission['key']] = $entry;
                    }
                } else {
                    // مش موجودة → default
                    $entry = ['granted' => 0];
                    foreach ($modes as $mode) {
                        $entry[$mode] = 0;
                    }
                    $permissions[$category_key][$permission['key']] = $entry;
                }
            }

            $permissions[$category_key] = json_encode($permissions[$category_key]);
        }

        $request['default_permissions'] = json_encode($permissions);
        $role->fill($request)->save();

        // تحديث صلاحيات الـ users بتاعين الـ role
        $users = User::where('role_id', $role->id)->get();
        foreach ($users as $user) {
            Permission::where('user_id', $user->id)->delete();

            $permissions_data = array_merge([
                'user_id' => $user->id,
                'role_id' => $user->role_id,
            ], $permissions);

            Permission::create($permissions_data);

            $user->discount_percentage = $role->discount_percentage;
            $user->save();
        }

        return $role;
    }
    
    public function delete(Role $role)
    {
        $role->delete();
    }

    public function transferUsersToNewRole($old_role_id, $new_role_id)
    {
        $newRole = Role::find($new_role_id);

        User::where('role_id', $old_role_id)
            ->update(['role_id' => $new_role_id]);

        Permission::where('role_id', $old_role_id)
            ->update(['role_id' => $new_role_id]);

        return true;
    }
}
