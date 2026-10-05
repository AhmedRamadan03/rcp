<?php

namespace App\Services;

use App\Models\GeneralPermission;
use App\Models\Permission;

class PermissionService
{
    public function list()
    {
        return Permission::all();
    }

    public function create($request)
    {
        $permissions = [];
        $general_permissions = GeneralPermission::select('permission as key', 'path', 'permission_mode')
            ->get()
            ->groupBy('path')
            ->toArray();

        foreach ($general_permissions as $category_key => $category) {
            foreach ($category as $permission) {
                $allowed_modes = $permission['permission_mode'] ?? [];
                $key = $permission['key'];

                if (isset($request[$category_key][$key])) {
                    $value = $request[$category_key][$key];

                    if (is_array($value)) {
                        $entry = ['granted' => (int) ($value['granted'] ?? 0)];

                        // أضف بس المفاتيح المسموحة لهذه الصلاحية حسب permission_mode
                        foreach ($allowed_modes as $mode) {
                            $entry[$mode] = (int) ($value[$mode] ?? 0);
                        }

                        $permissions[$category_key][$key] = $entry;
                    } else {
                        $permissions[$category_key][$key] = (int) $value;
                    }
                } else {
                    $entry = ['granted' => 0];
                    foreach ($allowed_modes as $mode) {
                        $entry[$mode] = 0;
                    }
                    $permissions[$category_key][$key] = $entry;
                }
            }
        }

        $permission_value = [
            'claims'        => json_encode($permissions['claims'] ?? []),
            'claim_status'  => json_encode($permissions['claim_status'] ?? []),
            'claims_files'  => json_encode($permissions['claims_files'] ?? []),
            'settings'      => json_encode($permissions['settings'] ?? []),
            'third_parties' => json_encode($permissions['third_parties'] ?? []),
            'users'         => json_encode($permissions['users'] ?? []),
            'permissions'   => json_encode($permissions['permissions'] ?? []),
            'transactions'  => json_encode($permissions['transactions'] ?? []),
            'reports'       => json_encode($permissions['reports'] ?? []),
            'role'          => json_encode($permissions['role'] ?? []),
            'accounting'    => json_encode($permissions['accounting'] ?? []),
            'dashboard'     => json_encode($permissions['dashboard'] ?? []),
            'payments'      => json_encode($permissions['payments'] ?? []),
            'user_id'       => $request['user_id'],
            'role_id'       => $request['role_id'],
        ];

        return Permission::updateOrCreate(
            ['user_id' => $request['user_id']],
            $permission_value
        )->refresh();
    }
}