<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Permission;
use App\Models\Role;
use App\Enums\Roles;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RoleSeederOld extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Role::truncate();
        Role::create([
            //'id' => Roles::SUPER_ADMIN->value,
            'title' => 'super admin',
            'description' => 'super admin role description',
            'discount_percentage' => '10.00',
            'default_permissions' => json_encode(default_permission_for_super_admin()),
        ]);

        Role::create([
            //'id' => Roles::SUPERVISOR->value,
            'title' => 'supervisor',
            'description' => 'supervisor role description',
            'discount_percentage' => '10.00',
            'default_permissions' => json_encode(default_permission_for_supervisor()),
        ]);

        Role::create([
            //'id' => Roles::COLLECTOR->value,
            'title' => 'collector',
            'description' => 'collector role description',
            'discount_percentage' => '10.00',
            'default_permissions' => json_encode(default_permission_for_collector()),
        ]);

        Role::create([
            //'id' => Roles::EXTERNAL_OFFICE->value,
            'title' => 'external office',
            'description' => 'external office role description',
            'discount_percentage' => '10.00',
            'default_permissions' => json_encode(default_permission_for_external_office()),
        ]);

        Role::create([
            //'id' => Roles::LEGAL_DEPARTMENT->value,
            'title' => 'legal department user',
            'description' => 'Legal department user',
            'discount_percentage' => '10.00',
            'default_permissions' => json_encode(default_permission_for_external_office()),
        ]);

        Role::create([
            //'id' => Roles::TAHEIYA_ADMIN->value,
            'title' => 'taheiya admin',
            'description' => 'Taheiya admin role description',
            'discount_percentage' => '10.00',
            'default_permissions' => json_encode(default_permission_for_super_admin()),
        ]);

        Role::create([
            //'id'=> Roles::OTHERS->value,
            'title'=> 'others users role',
            'description' => 'others users role description',
            'discount_percentage' => '10.00',
            'default_permissions' => json_encode(default_permission(Roles::OTHERS->value)),
        ]);

        Role::create([
            //'id' => Roles::SENIOR_COLLECTOR->value,
            'title' => 'senior_collector',
            'description' => 'senior collector user',
            'discount_percentage' => '10.00',
            'default_permissions' => json_encode(default_permission_for_senior_collector()),
        ]);

        Client::factory()->create([
            'title' => 'Taheiya Tech',
            'subtitle' => 'Taheiya Tech',
            'slug' => 'taheiya-tech',
            'email' => 'taheiya@mail.com',
            'phone' => '0512345678',
        ]);

        //        \App\Models\User::factory(10)->create();
        \App\Models\User::factory()->create([
            'name' => 'Client user',
            'client_id' => 1,
            'email' => 'admin@mail.com',
            'password' => Hash::make('password'),
            'role_id' => 1,
            'discount_percentage' => 99,
        ]);

        $users = User::all();

        foreach ($users as $user) {
            $this->create_permission($user);
        }
    }

    public function create_permission($user)
    {

        if ($user->role_id >= 5) {
            $user->role_id = 4;
            $user->save();
        }
        $permissions = [
            'user_id' => $user->id,
            'role_id' => $user->role_id,
        ]; // default_per()

        //        dd(default_permission($user->role_id));
        //        if(default_permission($user->role_id) == null) {
        //            dd($user);
        //        }
        $permissions = array_merge($permissions, default_permission($user->role_id));
        Permission::create(

            $permissions
        );
    }
}
