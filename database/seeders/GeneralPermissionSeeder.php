<?php

namespace Database\Seeders;

use App\Enums\PermissionEnum;
use App\Enums\Roles;
use App\Models\GeneralPermission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GeneralPermissionSeeder extends Seeder
{
    public $permissions = [];

    public function initPermissions()
    {
        $this->permissions = [
            ['permission' => 'create_claim', 'type' => PermissionEnum::PUBLIC->value, 'path' => 'claims'],
            ['permission' => 'show_claim',  'type' => PermissionEnum::PUBLIC->value, 'path' => 'claims'],
            ['permission' => 'show_all_claims',  'type' => PermissionEnum::PUBLIC->value, 'path' => 'claims'],
            ['permission' => 'update_claim', 'type' => PermissionEnum::PUBLIC->value, 'path' => 'claims'],
            ['permission' => 'manage_client_requests', 'type' => PermissionEnum::PUBLIC->value, 'path' => 'claims'],
            ['permission' => 'assign_claim', 'type' => PermissionEnum::PUBLIC->value, 'path' => 'claims'],
            ['permission' => 'approve_claim', 'type' => PermissionEnum::PUBLIC->value, 'path' => 'claims'],
            ['permission' => 'generate_discharge_letter', 'permission_mode' => ['with_approve'], 'type' => PermissionEnum::PUBLIC->value, 'path' => 'claims'],
            ['permission' => 'download_discharge_letter', 'type' => PermissionEnum::PUBLIC->value, 'path' => 'claims'],
            ['permission' => 'send_discharge_letter',  'permission_mode' => ['with_approve'], 'type' => PermissionEnum::PUBLIC->value, 'path' => 'claims'],
            ['permission' => 'generate_voucher_letter', 'permission_mode' => ['with_approve'], 'type' => PermissionEnum::PUBLIC->value, 'path' => 'claims'],
            ['permission' => 'download_voucher_letter', 'type' => PermissionEnum::PUBLIC->value, 'path' => 'claims'],
            ['permission' => 'send_voucher_letter',  'permission_mode' => ['with_approve'], 'type' => PermissionEnum::PUBLIC->value, 'path' => 'claims'],
            ['permission' => 'update_claim_amount','permission_mode' => ['with_approve'], 'type' => PermissionEnum::PUBLIC->value, 'path' => 'claims'],
            ['permission' => 'dispute_payment', 'permission_mode' => ['with_approve'], 'type' => PermissionEnum::PUBLIC->value, 'path' => 'claims'],
            ['permission' => 'import', 'type' => PermissionEnum::PUBLIC->value, 'path' => 'claims'],
            ['permission' => 'export', 'type' => PermissionEnum::PUBLIC->value, 'path' => 'claims'],


            ['permission' => 'canceled',  'type' => PermissionEnum::PUBLIC->value, 'path' => 'claim_status'],
            ['permission' => 'collected_out_of_system', 'permission_mode' => ['with_approve'], 'type' => PermissionEnum::PUBLIC->value, 'path' => 'claim_status'],
            ['permission' => 'transfer_to_legal', 'permission_mode' => ['with_approve'], 'type' => PermissionEnum::PUBLIC->value, 'path' => 'claim_status'],
            ['permission' => 'default_in_payment_manual', 'permission_mode' => ['with_approve'], 'type' => PermissionEnum::PUBLIC->value, 'path' => 'claim_status'],
            ['permission' => 'collected_credited', 'permission_mode' => ['with_approve'], 'type' => PermissionEnum::PUBLIC->value, 'path' => 'claim_status'],
            ['permission' => 'close_claim', 'permission_mode' => ['with_approve'], 'type' => PermissionEnum::PUBLIC->value, 'path' => 'claim_status'],
            ['permission' => 'back_claim_to_original_status', 'permission_mode' => ['with_approve'], 'type' => PermissionEnum::PUBLIC->value, 'path' => 'claim_status'],


            ['permission' => 'create_claims_files', 'type' => PermissionEnum::PUBLIC->value, 'path' => 'claims_files'],
            ['permission' => 'show_claims_files', 'type' => PermissionEnum::PUBLIC->value, 'path' => 'claims_files'],
            ['permission' => 'delete_claims_files', 'type' => PermissionEnum::PUBLIC->value, 'path' => 'claims_files'],

            ['permission' => 'show_settings', 'type' => PermissionEnum::PUBLIC->value, 'path' => 'settings'],
            ['permission' => 'create_settings', 'type' => PermissionEnum::PUBLIC->value, 'path' => 'settings'],
            ['permission' => 'update_settings', 'type' => PermissionEnum::PUBLIC->value, 'path' => 'settings'],
            ['permission' => 'delete_settings', 'type' => PermissionEnum::PUBLIC->value, 'path' => 'settings'],
            ['permission' => 'show_api_credential', 'type' => PermissionEnum::PUBLIC->value, 'path' => 'settings'],
            ['permission' => 'manage_api_credential', 'type' => PermissionEnum::PUBLIC->value, 'path' => 'settings'],

            ['permission' => 'show_third_parties', 'type' => PermissionEnum::PUBLIC->value, 'path' => 'third_parties'],
            ['permission' => 'create_third_parties', 'type' => PermissionEnum::PUBLIC->value, 'path' => 'third_parties'],
            ['permission' => 'update_third_parties', 'type' => PermissionEnum::PUBLIC->value, 'path' => 'third_parties'],
            ['permission' => 'delete_third_parties', 'type' => PermissionEnum::PUBLIC->value, 'path' => 'third_parties'],
            ['permission' => 'activate_third_parties', 'type' => PermissionEnum::PUBLIC->value, 'path' => 'third_parties'],

            ['permission' => 'show_users', 'type' => PermissionEnum::PUBLIC->value, 'path' => 'users'],
            ['permission' => 'create_users', 'type' => PermissionEnum::PUBLIC->value, 'path' => 'users'],
            ['permission' => 'update_users', 'type' => PermissionEnum::PUBLIC->value, 'path' => 'users'],
            ['permission' => 'delete_users', 'type' => PermissionEnum::PUBLIC->value, 'path' => 'users'],
            ['permission' => 'activate_users', 'type' => PermissionEnum::PUBLIC->value, 'path' => 'users'],

            ['permission' => 'update_permissions', 'type' => PermissionEnum::PUBLIC->value, 'path' => 'permissions'],
            ['permission' => 'show_private_permissions', 'type' => PermissionEnum::PRIVATE->value, 'path' => 'permissions'],

            ['permission' => 'show_role', 'type' => PermissionEnum::PUBLIC->value, 'path' => 'role'],
            ['permission' => 'create_role', 'type' => PermissionEnum::PUBLIC->value, 'path' => 'role'],
            ['permission' => 'update_role', 'type' => PermissionEnum::PUBLIC->value, 'path' => 'role'],

            ['permission' => 'show_all_transactions', 'type' => PermissionEnum::PUBLIC->value, 'path' => 'transactions'],

            ['permission' => 'show_reports', 'type' => PermissionEnum::PUBLIC->value, 'path' => 'reports'],

            ['permission' => 'delay_payments',  'permission_mode' => ['with_approve'],'type' => PermissionEnum::PUBLIC->value, 'path' => 'payments'],
            ['permission' => 'installment_payments', 'permission_mode' => ['with_approve'], 'type' => PermissionEnum::PUBLIC->value, 'path' => 'payments'],


            // dashboard permissions
            ['permission' => 'show_all_executive', 'type' => PermissionEnum::PUBLIC->value, 'path' => 'dashboard'],

            //private permissions
            ['permission' => 'show_accounting', 'type' => PermissionEnum::PRIVATE->value, 'path' => 'accounting'],

        ];
    }


    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        GeneralPermission::truncate();
        $this->initPermissions();
            foreach ($this->permissions as $permission) {
            if (!DB::table('general_permissions')->where('permission', $permission['permission'])
                ->where('path', $permission['path'])->exists()) {
                GeneralPermission::create([
                        'permission' => $permission['permission'],
                        'type' => $permission['type'],
                    'path' => $permission['path'],
                    'permission_mode' => isset($permission['permission_mode']) ? $permission['permission_mode']: null,
                ]);
            }
        }

        $admin = \App\Models\User::withTrashed()->firstOrCreate(
            ['email' => 'admin@taheiya.sa'],
            [
                'name' => 'Admin',
                'password' => bcrypt('password'),
                'phone' => '0000000000',
                'role_id' => Roles::SUPER_ADMIN->value,
                'first_time_login' => false,
                'client_id' => 1,
                'discount_percentage' => 100,
            ]
        );

        if ($admin->trashed()) {
            $admin->restore();
        }

        if ($admin) {

            $permissionsByPath = [];
            $allPermissions = GeneralPermission::all();

            foreach ($allPermissions as $perm) {
                $value = ['granted' => 1];

                $modes = is_array($perm->permission_mode)
                    ? $perm->permission_mode
                    : json_decode($perm->permission_mode ?? '[]', true);

                if (in_array('with_approve', $modes ?? [])) {
                    $value['with_approve'] = 0; // الأدمن مش محتاج موافقة أصلاً
                }

                $permissionsByPath[$perm->path][$perm->permission] = $value;
            }
            // تحديث صلاحيات الأدمن بالكامل
            $admin->permission()->updateOrCreate(
                [
                    'user_id' => $admin->id,
                    'role_id' => $admin->role_id,
                ],
                [
                    'claims' => json_encode($permissionsByPath['claims'] ?? []),
                    'claim_status' => json_encode($permissionsByPath['claim_status'] ?? []), // ✅ الـ path الجديد
                    'claims_files' => json_encode($permissionsByPath['claims_files'] ?? []),
                    'settings' => json_encode($permissionsByPath['settings'] ?? []),
                    'third_parties' => json_encode($permissionsByPath['third_parties'] ?? []),
                    'users' => json_encode($permissionsByPath['users'] ?? []),
                    'permissions' => json_encode($permissionsByPath['permissions'] ?? []),
                    'role' => json_encode($permissionsByPath['role'] ?? []),
                    'transactions' => json_encode($permissionsByPath['transactions'] ?? []),
                    'reports' => json_encode($permissionsByPath['reports'] ?? []),
                    'payments' => json_encode($permissionsByPath['payments'] ?? []),
                    'accounting' => json_encode($permissionsByPath['accounting'] ?? []),
                    'dashboard' => json_encode($permissionsByPath['dashboard'] ?? []),
                ]
            );
        }
    }
}