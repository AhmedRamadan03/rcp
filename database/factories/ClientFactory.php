<?php

namespace Database\Factories;

use App\Enums\ClientType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $c_name = fake()->colorName();

        return [
            'type' => ClientType::INSURANCE_COMPANY,
            'title' => $c_name,
            'subtitle' => $c_name.' subtitle',
            'slug' => $c_name.'-'.fake()->slug(),
            'email' => $c_name.'@email.com',
            'phone' => fake()->phoneNumber(),
            'mobile' => fake()->phoneNumber(),
            'address' => fake()->address(),
        ];
    }
}
