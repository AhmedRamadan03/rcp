<?php

namespace Database\Factories;

use App\Enums\ClaimInsertionType;
use App\Enums\ClaimStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Cliam>
 */
class ClaimFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $star_date = Carbon::instance(fake()->dateTimeBetween('-2 months', '-1 months'));

        return [
            'client_id' => 1,
            'city_id' => fake()->numberBetween(1, 10),
            'amount' => fake()->numberBetween(7600.00, 1865.99),
            'user_id' => 1,
            'insertion_type' => ClaimInsertionType::FORM->value,
            'debtor_type' => 'insured',
            'policy_number' => fake()->numberBetween(34785643, 347560000000),
            'claim_number' => fake()->numberBetween(90000, 500000000),
            'debtor_name' => fake()->name(),
            'debtor_nid' => fake()->numberBetween(80000, 340000000),
            'is_transformed_to_lawyer' => fake()->boolean(),
            'is_transformed_to_naviz' => fake()->boolean(),
            'return_reason' => fake()->numberBetween(0, 10),
            'debtor_phone' => fake()->phoneNumber(),
            'accident_date' => fake()->dateTime(),
            'created_at' => (clone $star_date)->addDays(random_int(0, 14)),
            'updated_at' => (clone $star_date)->addDays(random_int(0, 14)),
            'status' => ClaimStatus::REGISTERED->value,
        ];
    }
}
