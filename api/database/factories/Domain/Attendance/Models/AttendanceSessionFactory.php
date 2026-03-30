<?php

namespace Database\Factories\Domain\Attendance\Models;

use App\Domain\Attendance\Models\AttendanceSession;
use App\Models\Hostel;
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
        $tenant = Tenant::factory()->create();

        // HostelFactory will auto-create campus via configure() method
        $hostel = Hostel::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        return [
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'session_date' => now()->toDateString(), // Required column
            'session_time' => now()->toTimeString(), // Required column
            'status' => 'in_progress', // Must be one of: pending, in_progress, completed
            'started_by' => null,
            'started_at' => now()->subMinutes(10),
            'completed_at' => null,
        ];
    }
}
