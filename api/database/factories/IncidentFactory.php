<?php

namespace Database\Factories;

use App\Models\Incident;
use App\Models\Hostel;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class IncidentFactory extends Factory
{
    protected $model = Incident::class;

    public function definition(): array
    {
        return [
            'hostel_id' => Hostel::factory(),
            'type' => $this->faker->randomElement([
                Incident::TYPE_LATE_RETURN,
                Incident::TYPE_MISSED_ATTENDANCE,
                Incident::TYPE_EMERGENCY_EXIT,
                Incident::TYPE_SECURITY,
            ]),
            'student_id' => Student::factory(),
            'note' => $this->faker->sentence(),
            'status' => $this->faker->randomElement(['Open', 'Closed']),
            'opened_by' => User::factory(),
            'opened_at' => now(),
            'metadata' => [],
        ];
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Open',
            'closed_by' => null,
            'closed_at' => null,
            'closure_note' => null,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'Closed',
            'closed_by' => User::factory(),
            'closed_at' => now(),
            'closure_note' => $this->faker->sentence(),
        ]);
    }
}

