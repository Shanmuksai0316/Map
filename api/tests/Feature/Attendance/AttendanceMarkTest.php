<?php

namespace Tests\Feature\Attendance;

use App\Domain\Attendance\Models\AttendanceMark;
use App\Domain\Attendance\Models\AttendanceSession;
use App\Models\Domain\OutPass\OutPass;
use App\Models\Hostel;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tests\Support\AttendanceTestHelpers;

class AttendanceMarkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Warden', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Warden', 'guard_name' => 'sanctum']);
        Role::firstOrCreate(['name' => 'Student', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Student', 'guard_name' => 'sanctum']);
    }

    public function test_warden_can_mark_student_present(): void
    {
        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create([
            'tenant_id' => $tenant->id,
            'curfew_time' => '22:30:00',
        ]);
        $room = Room::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
        ]);
        $warden = User::factory()->create([
            'tenant_id' => $tenant->id,
            'kind' => 'Warden',
        ]);
        $warden->assignRole('Warden');

        $student = User::factory()->create([
            'tenant_id' => $tenant->id,
            'kind' => 'Student',
        ]);

        $roomData = AttendanceTestHelpers::createRoomWithBedsAndAllocations($tenant, $hostel, $room, collect([$student]));
        $studentRecord = $roomData['students'][0];

        // Create session
        $session = AttendanceSession::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'kind' => 'night_check',
            'status' => 'open',
            'scheduled_at' => Carbon::now('Asia/Kolkata'),
            'metadata' => [
                'open_at' => Carbon::now('Asia/Kolkata')->subHour()->toISOString(),
                'close_at' => Carbon::now('Asia/Kolkata')->addHour()->toISOString(),
            ],
        ]);

        Sanctum::actingAs($warden, ['*']);

        $response = $this->postJson("/api/v1/attendance/sessions/{$session->id}/rooms/{$room->id}/mark", [
            'student_id' => $student->id,
            'status' => 'present',
        ]);

        $response->assertOk()
            ->assertJson(['status' => 'ok']);

        $this->assertDatabaseHas('attendance_logs', [
            'attendance_session_id' => $session->id,
            'student_id' => $studentRecord->id,
            'status' => 'present',
            'marked_by' => $warden->id,
        ]);
    }

    public function test_warden_can_mark_student_absent_with_comment(): void
    {
        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create([
            'tenant_id' => $tenant->id,
            'curfew_time' => '22:30:00',
        ]);
        $room = Room::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
        ]);
        $warden = User::factory()->create([
            'tenant_id' => $tenant->id,
            'kind' => 'Warden',
        ]);
        $warden->assignRole('Warden');

        $student = User::factory()->create([
            'tenant_id' => $tenant->id,
            'kind' => 'Student',
        ]);

        $roomData = AttendanceTestHelpers::createRoomWithBedsAndAllocations($tenant, $hostel, $room, collect([$student]));
        $studentRecord = $roomData['students'][0];

        // Create session
        $session = AttendanceSession::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'kind' => 'night_check',
            'status' => 'open',
            'scheduled_at' => Carbon::now('Asia/Kolkata'),
            'metadata' => [
                'open_at' => Carbon::now('Asia/Kolkata')->subHour()->toISOString(),
                'close_at' => Carbon::now('Asia/Kolkata')->addHour()->toISOString(),
            ],
        ]);

        Sanctum::actingAs($warden, ['*']);

        $response = $this->postJson("/api/v1/attendance/sessions/{$session->id}/rooms/{$room->id}/mark", [
            'student_id' => $student->id,
            'status' => 'absent',
            'comment' => 'Not in room',
        ]);

        $response->assertOk()
            ->assertJson(['status' => 'ok']);

        $this->assertDatabaseHas('attendance_logs', [
            'attendance_session_id' => $session->id,
            'student_id' => $studentRecord->id,
            'status' => 'absent',
            'note' => 'Not in room',
            'marked_by' => $warden->id,
        ]);
    }

    public function test_roster_shows_leave_students_as_locked(): void
    {
        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create([
            'tenant_id' => $tenant->id,
            'curfew_time' => '22:30:00',
        ]);
        $room = Room::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
        ]);
        $warden = User::factory()->create([
            'tenant_id' => $tenant->id,
            'kind' => 'Warden',
        ]);
        $warden->assignRole('Warden');

        $studentA = User::factory()->create([
            'tenant_id' => $tenant->id,
            'kind' => 'Student',
        ]);
        $studentB = User::factory()->create([
            'tenant_id' => $tenant->id,
            'kind' => 'Student',
        ]);

        $roomData = AttendanceTestHelpers::createRoomWithBedsAndAllocations($tenant, $hostel, $room, collect([$studentA, $studentB]));

        // Create session
        $session = AttendanceSession::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'kind' => 'night_check',
            'status' => 'open',
            'scheduled_at' => Carbon::now('Asia/Kolkata'),
            'metadata' => [
                'open_at' => Carbon::now('Asia/Kolkata')->subHour()->toISOString(),
                'close_at' => Carbon::now('Asia/Kolkata')->addHour()->toISOString(),
            ],
        ]);

        // Create an approved OutPass for studentA overlapping the session
        OutPass::factory()->create([
            'tenant_id' => $tenant->id,
            'student_id' => $roomData['students'][0]->id, // Use Student ID, not User ID
            'hostel_id' => $hostel->id,
            'status' => 'approved',
            'requested_at' => $session->metadata['open_at'],
            'valid_until' => $session->metadata['close_at'],
        ]);

        Sanctum::actingAs($warden, ['*']);

        $response = $this->getJson("/api/v1/attendance/sessions/{$session->id}/rooms/{$room->id}");

        $response->assertOk()
            ->assertJsonFragment([
                'student_id' => $studentA->id,
                'current_status' => 'leave',
                'locked' => true,
            ])
            ->assertJsonFragment([
                'student_id' => $studentB->id,
                'current_status' => 'unmarked',
                'locked' => false,
            ]);
    }

    public function test_cannot_mark_student_on_leave(): void
    {
        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create([
            'tenant_id' => $tenant->id,
            'curfew_time' => '22:30:00',
        ]);
        $room = Room::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
        ]);
        $warden = User::factory()->create([
            'tenant_id' => $tenant->id,
            'kind' => 'Warden',
        ]);
        $warden->assignRole('Warden');

        $student = User::factory()->create([
            'tenant_id' => $tenant->id,
            'kind' => 'Student',
        ]);

        $roomData = AttendanceTestHelpers::createRoomWithBedsAndAllocations($tenant, $hostel, $room, collect([$student]));

        // Create session
        $session = AttendanceSession::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'kind' => 'night_check',
            'status' => 'open',
            'scheduled_at' => Carbon::now('Asia/Kolkata'),
            'metadata' => [
                'open_at' => Carbon::now('Asia/Kolkata')->subHour()->toISOString(),
                'close_at' => Carbon::now('Asia/Kolkata')->addHour()->toISOString(),
            ],
        ]);

        // Create an approved OutPass for student overlapping the session
        OutPass::factory()->create([
            'tenant_id' => $tenant->id,
            'student_id' => $roomData['students'][0]->id, // Use Student ID, not User ID
            'hostel_id' => $hostel->id,
            'status' => 'approved',
            'requested_at' => $session->metadata['open_at'],
            'valid_until' => $session->metadata['close_at'],
        ]);

        Sanctum::actingAs($warden, ['*']);

        $response = $this->postJson("/api/v1/attendance/sessions/{$session->id}/rooms/{$room->id}/mark", [
            'student_id' => $student->id,
            'status' => 'present',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['student_id' => 'Cannot mark student who is on approved leave']);
    }
}