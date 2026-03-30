<?php

namespace Database\Factories;

use App\Models\SportsEvent;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SportsEvent>
 */
class SportsEventFactory extends Factory
{
    protected $model = SportsEvent::class;

    public function definition(): array
    {
        return [
                        'sport' => $this->faker->randomElement(['Football', 'Basketball', 'Badminton']),
            'name' => $this->faker->sentence(3),
            'scheduled_at' => now()->addDays(3),
            'status' => 'scheduled',
        ];
    }
}
