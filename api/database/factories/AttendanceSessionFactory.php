<?php

namespace Database\Factories;

use App\Domain\Attendance\Models\AttendanceSession;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceSession>
 */
class AttendanceSessionFactory extends Factory
{
    protected $model = AttendanceSession::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'hostel_id' => null,
            'name' => $this->faker->sentence(3),
            'kind' => $this->faker->randomElement(['roll_call', 'event', 'night_check']),
            'session_date' => now()->toDateString(),
            'session_time' => now()->format('H:i:s'),
            'scheduled_at' => now()->addHour(),
            'status' => 'pending',
            'metadata' => [
                'open_at' => now()->toISOString(),
                'close_at' => now()->addHours(2)->toISOString(),
                'session_date' => now()->toDateString(),
            ],
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (AttendanceSession $session) {
            if (!$session->tenant_id) {
                $session->tenant_id = app()->bound('testing.default_tenant_id')
                    ? app('testing.default_tenant_id')
                    : Tenant::factory()->create()->id;
            }

            if (!$session->hostel_id) {
                $hostel = \App\Models\Hostel::factory()->create([
                    'tenant_id' => $session->tenant_id,
                ]);

                $session->hostel_id = $hostel->id;
            }
        });
    }
}
