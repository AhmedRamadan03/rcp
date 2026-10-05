<?php

namespace Database\Seeders;

use App\Models\RejectedClaim;
use Illuminate\Database\Seeder;

class RejectedClaimSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        //
        RejectedClaim::factory()->count(100)->create();
    }
}
