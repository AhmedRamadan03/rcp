<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CollectorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $collectors = [
            [
                'name' => 'Collector 1',
                'email' => 'collector1@example.com',
                'password' => 'password',
                'role_id' => 3,
                'supervisor_id' => 1,
                'client_id' => 1,
            ],
            [
                'name' => 'Collector 2',
                'email' => 'collector2@example.com',
                'password' => 'password',
                'role_id' => 3,
                'supervisor_id' => 1,
                'client_id' => 1,
            ],
            [
                'name' => 'Collector 3',
                'email' => 'collector3@example.com',
                'password' => 'password',
                'role_id' => 3,
                'supervisor_id' => 1,
                'client_id' => 1,
            ],
            [
                'name' => 'Collector 4',
                'email' => 'collector4@example.com',
                'password' => 'password',
                'role_id' => 3,
                'supervisor_id' => 1,
                'client_id' => 1,
            ],
            [
                'name' => 'Collector 5',
                'email' => 'collector5@example.com',
                'password' => 'password',
                'role_id' => 3,
                'supervisor_id' => 1,
                'client_id' => 1,
            ],



        ];

        foreach ($collectors as $collector) {
            User::create($collector);
        }
    }
}
