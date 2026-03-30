<?php

namespace Database\Factories\Domain\SickLeaves;

use App\Domain\SickLeaves\Models\SickLeave;
use App\Models\Hostel;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SickLeave>
 */
class SickLeaveFactory extends Factory
{
    protected $model = SickLeave::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'hostel_id' => Hostel::factory(),
            'unique_id' => 'SLK-' . strtoupper(Str::random(8)),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'illness' => $this->faker->randomElement([
                'Fever and Cold',
                'Headache',
                'Stomach Pain',
                'Cough',
                'Dizziness',
            ]),
            'illness_details' => $this->faker->paragraph(),
            'need_medical_attention' => $this->faker->boolean(),
            'contact_parents' => $this->faker->boolean(),
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
            'approved_by' => $this->faker->numberBetween(1, 100),
            'approved_at' => $this->faker->dateTimeBetween('-1 day', 'now'),
            'rejection_reason' => null,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'approved_by' => $this->faker->numberBetween(1, 100),
            'approved_at' => $this->faker->dateTimeBetween('-1 day', 'now'),
            'rejection_reason' => $this->faker->sentence(),
        ]);
    }
}

