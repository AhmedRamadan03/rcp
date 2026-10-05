<?php

namespace Database\Seeders;

use App\Enums\Roles;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class TaheiyaUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        // move to role seeder
        // Role::UpdateOrCreate([
        //     'id' => Roles::TAHEIYA_ADMIN->value,
        //     'title' => 'taheiya admin',
        // ], [
        //     'id' => Roles::TAHEIYA_ADMIN->value,
        //     'title' => 'taheiya admin',
        //     'description' => 'Taheiya admin role description',
        //     'discount_percentage' => '10.00',
        //     'default_permissions' => json_encode(default_permission_for_super_admin()),
        // ]);
        $userExists = \App\Models\User::where('email', 'hazim@taheiya.sa')->first();
        if (!$userExists ) {
            # code...
            $user = \App\Models\User::create([
                'name' => 'Taheiya',
                'client_id' => 1,
                'email' => 'hazim@taheiya.sa',
                'password' => Hash::make('password'),
                'role_id' => Roles::TAHEIYA_ADMIN->value,
            ]);

            $permissions = [
                'user_id' => $user->id,
                'role_id' => $user->role_id,
            ];

            $permissions = array_merge($permissions, default_permission($user->role_id));



            Permission::create(

                $permissions
            );
        }
    }
}
