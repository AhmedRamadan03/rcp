<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

use App\Enums\Roles;
use App\Models\City;
use App\Models\CustomTemplate;
use App\Services\DataMigrationFromOld;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {

        //
        //

        //        \App\Models\Client::factory(2)->create();
        //        \App\Models\City::factory(12)->create();
        //

        //
        //        \App\Models\User::factory()->create([
        //            'name' => 'User One',
        //            'client_id' => 1,
        //            'password' => Hash::make('password')
        //        ]);
        //
        //
        //        \App\Models\User::factory()->create([
        //            'name' => 'Supervisor One',
        //            'client_id' => 1,
        //            'role_id' => Roles::SUPERVISOR,
        //            'password' => Hash::make('password')
        //        ]);
        //
        //
        //        \App\Models\User::factory()->create([
        //            'name' => 'Supervisor One',
        //            'client_id' => 1,
        //            'role_id' => Roles::EMPLOYEE,
        //            'password' => Hash::make('password')
        //        ]);
        //
        //
        //
        //        \App\Models\User::factory()->create([
        //            'name' => 'Supervisor One',
        //            'client_id' => 1,
        //            'role_id' => Roles::SUPERVISOR,
        //            'password' => Hash::make('password')
        //        ]);

        //         \App\Models\Setting::create([
        //             "Language" => json_encode(["Arabic", "English"]),
        //         ]);

        // \App\Models\Claim::factory(1000)->create();

        // DataMigrationFromOld::run();

        $this->call([
            CitiesSeeder::class,
            RoleSeeder::class,
            TaheiyaUserSeeder::class,
            GeneralPermissionSeeder::class,
            CategoryMessageVariablesSeeder::class,
            DefaultMessageTemplateSeeder::class,
        ]);
      


        //        $this->call([
        //           RejectedClaimSeeder::class
        //        ]);

        //        Schema::disableForeignKeyConstraints();
        //        City::truncate();
        //        Schema::enableForeignKeyConstraints();

    }
}
