<?php

namespace Database\Factories;

use App\Domain\Attendance\Models\AttendanceMark;
use App\Domain\Attendance\Models\AttendanceSession;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceMark>
 */
class AttendanceMarkFactory extends Factory
{
    protected $model = AttendanceMark::class;

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
            'marked_by' => User::factory(),
        ];
    }
}
