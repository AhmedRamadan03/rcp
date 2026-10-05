<?php

namespace Database\Seeders;

use App\Models\Claim;
use Illuminate\Database\Seeder;

class CliamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Claim::factory()->count(10)->create();
    }
}
