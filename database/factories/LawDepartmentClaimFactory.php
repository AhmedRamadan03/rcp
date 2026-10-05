<?php

namespace Database\Factories;

use App\Models\Claim;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LawDepartmentClaim>
 */
class LawDepartmentClaimFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'claim_id' => fake()->numberBetween(1, 10),
            'user_id' => fake()->numberBetween(1, 10),
            'request_id' => fake()->numberBetween(1, 10),
            'created_at' => fake()->dateTime(),
            'updated_at' => fake()->dateTime(),
        ];
    }
}
