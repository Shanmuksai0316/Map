<?php

namespace Database\Factories\Domain\RoomChanges;

use App\Domain\RoomChanges\Models\RoomChange;
use App\Models\Hostel;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<RoomChange>
 */
class RoomChangeFactory extends Factory
{
    protected $model = RoomChange::class;

    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'hostel_id' => Hostel::factory(),
            'unique_id' => 'RMC-' . strtoupper(Str::random(8)),
            'title' => 'Room Change Request',
            'description' => $this->faker->paragraph(),
            'preferred_room_number' => $this->faker->optional()->numerify('###'),
            'preferred_floor' => $this->faker->optional()->randomElement(['1st Floor', '2nd Floor', '3rd Floor', 'Ground Floor']),
            'sharing_preference' => $this->faker->optional()->randomElement(['single', 'double', 'triple', 'quad']),
            'date_required' => $this->faker->optional()->dateTimeBetween('now', '+1 month'),
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

