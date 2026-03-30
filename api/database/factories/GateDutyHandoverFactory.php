<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GateDutyHandover>
 */
class GateDutyHandoverFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $shiftStart = now()->addHours(rand(1, 8));
        $shiftEnd = $shiftStart->copy()->addHours(8);

        return [
                        'hostel_id' => \App\Models\Hostel::factory(),
            'guard_id' => \App\Models\User::factory(),
            'shift_start' => $shiftStart,
            'shift_end' => $shiftEnd,
            'notes' => $this->faker->sentence(),
            'status' => 'active',
            'incidents_count' => $this->faker->numberBetween(0, 5),
            'entries_processed' => $this->faker->numberBetween(10, 100),
            'issues_reported' => $this->faker->optional()->paragraph(),
        ];
    }
}
