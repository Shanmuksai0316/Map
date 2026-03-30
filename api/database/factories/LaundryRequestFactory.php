<?php

namespace Database\Factories;

use App\Models\LaundryRequest;
use App\Models\Student;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LaundryRequest>
 */
class LaundryRequestFactory extends Factory
{
    protected $model = LaundryRequest::class;

    public function definition(): array
    {
        return [
                        'student_id' => Student::factory(),
            'service_type' => $this->faker->randomElement(['wash_only', 'wash_and_iron', 'dry_clean']),
            'status' => 'pending',
            'bag_count' => $this->faker->numberBetween(1, 3),
            'requested_at' => now(),
        ];
    }
}
