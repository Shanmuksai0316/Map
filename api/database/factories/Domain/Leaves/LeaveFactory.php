<?php

namespace Database\Factories\Domain\Leaves;

use App\Domain\Leaves\Models\Leave;
use App\Models\Hostel;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Leave>
 */
class LeaveFactory extends Factory
{
    protected $model = Leave::class;

    public function definition(): array
    {
        $fromDate = $this->faker->dateTimeBetween('now', '+1 month');
        $toDate = (clone $fromDate)->modify('+' . $this->faker->numberBetween(1, 7) . ' days');

        return [
            'student_id' => Student::factory(),
            'hostel_id' => Hostel::factory(),
            'unique_id' => 'LEV-' . strtoupper(Str::random(8)),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'reason_for_leave' => $this->faker->randomElement([
                'Family Emergency',
                'Medical',
                'Personal Work',
                'Festival',
                'Wedding',
            ]),
            'from_date' => $fromDate,
            'to_date' => $toDate,
            'emergency_contact' => $this->faker->optional()->phoneNumber(),
            'status' => $this->faker->randomElement(['pending', 'approved', 'rejected']),
            'rejection_reason' => null,
            'approved_by' => null,
            'approved_at' => null,
            'submitted_at' => now(),
            'idempotency_key' => $this->faker->optional()->uuid(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'approved_by' => null,
            'approved_at' => null,
            'rejection_reason' => null,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'approved_by' => $this->faker->numberBetween(1, 100), // User ID
            'approved_at' => $this->faker->dateTimeBetween('-1 day', 'now'),
            'rejection_reason' => null,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'approved_by' => $this->faker->numberBetween(1, 100), // User ID
            'approved_at' => $this->faker->dateTimeBetween('-1 day', 'now'),
            'rejection_reason' => $this->faker->sentence(),
        ]);
    }
}

