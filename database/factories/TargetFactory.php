<?php

namespace Database\Factories;

use App\Enums\Roles;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Target>
 */
class TargetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = Carbon::today()->firstOfMonth();
        $endDate = $startDate->clone()->endOfMonth();

        $target = $this->faker->randomNumber(6);
        $remaining = $this->faker->numberBetween(0, $target);
        $achieved = $target - $remaining;

        return [
            'start_date' => $startDate->format('d-m-Y'),
            'end_date' => $endDate->format('d-m-Y'),
            'user_id' => User::where('role_id', Roles::COLLECTOR->value)->get()->random()->id,
            'target' => $target,
            'remaining' => $remaining,
            'achieved' => $achieved,
        ];
    }
}
