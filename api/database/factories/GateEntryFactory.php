<?php

namespace Database\Factories;

use App\Domain\Gate\Models\GateEntry;
use App\Models\Hostel;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GateEntry>
 */
class GateEntryFactory extends Factory
{
    protected $model = GateEntry::class;

    public function definition(): array
    {
        $student = Student::factory()->create();
        $hostel = $student->hostel ?? Hostel::factory()->create(['tenant_id' => $student->tenant_id]);
        $occurredAt = now()->subMinutes($this->faker->numberBetween(5, 300));
        $wasOffline = $this->faker->boolean(20);

        return [
            'tenant_id' => $student->tenant_id,
            'campus_id' => $hostel->campus_id,
            'hostel_id' => $hostel->id,
            'student_id' => $student->id,
            'guard_user_id' => User::factory()->create(['tenant_id' => $student->tenant_id])->id,
            'event' => $this->faker->randomElement(['entry', 'exit']),
            'occurred_at' => $occurredAt,
            'source' => $wasOffline ? 'device_offline' : 'mobile',
            'direction' => $this->faker->randomElement(['in', 'out']),
            'method' => $this->faker->randomElement(['qr', 'otp', 'manual']),
            'verified' => true,
            'verified_at' => $occurredAt->copy()->addMinute(),
            'client_reference' => (string) Str::uuid(),
            'was_offline' => $wasOffline,
            'synced_at' => $wasOffline ? $occurredAt->copy()->addMinutes($this->faker->numberBetween(5, 20)) : $occurredAt,
            'late_minutes' => $this->faker->randomElement([null, $this->faker->numberBetween(0, 30)]),
            'notes' => $this->faker->optional()->sentence(),
            'metadata' => [
                'device' => $this->faker->randomElement(['Gate Tablet A', 'Gate Tablet B']),
            ],
        ];
    }
}
