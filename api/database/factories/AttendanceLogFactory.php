<?php

namespace Database\Factories;

use App\Models\AttendanceLog;
use App\Models\AttendanceSession;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceLog>
 */
class AttendanceLogFactory extends Factory
{
    protected $model = AttendanceLog::class;

    public function definition(): array
    {
        $session = AttendanceSession::factory()->create();
        $student = Student::factory()->create([
            'tenant_id' => $session->tenant_id,
            'hostel_id' => $session->hostel_id,
        ]);

        return [
            'tenant_id' => $session->tenant_id,
            'hostel_id' => $session->hostel_id,
            'attendance_session_id' => $session->id,
            'attendance_date' => $session->session_date ?? now()->toDateString(),
            'student_id' => $student->id,
            'status' => $this->faker->randomElement(['present', 'absent', 'late', 'excused']),
            'marked_at' => now(),
        ];
    }
}
