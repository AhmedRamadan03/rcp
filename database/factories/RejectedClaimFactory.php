<?php

namespace Database\Factories;

use App\Enums\RejectStatus;
use App\Enums\RejectType;
use App\Models\Claim;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RejectedClaim>
 */
class RejectedClaimFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            //
            'claim_id' => Claim::all()->random()->id,
            'user_id' => User::all()->random()->id,
            'supervisor_id' => User::all()->random()->id,
            'reason' => $this->faker->paragraph,
            'type' => RejectType::USER->value,
            'status' => $this->faker->randomElement(RejectStatus::class)->value,
        ];
    }
}
