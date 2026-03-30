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

class AttendanceSubmitRoomTest extends TestCase
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

    public function test_warden_can_submit_room_when_all_students_marked(): void
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

        $students = collect();
        for ($i = 0; $i < 3; $i++) {
            $students->push(User::factory()->create([
                'tenant_id' => $tenant->id,
                'kind' => 'Student',
            ]));
        }
        $roomData = AttendanceTestHelpers::createRoomWithBedsAndAllocations($tenant, $hostel, $room, $students);
        $studentRecords = $roomData['students'];

        Carbon::setTestNow(Carbon::create(2025, 9, 29, 21, 40, 0, 'Asia/Kolkata'));

        $session = AttendanceSession::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'kind' => 'night_check',
            'status' => 'open',
            'scheduled_at' => Carbon::now('Asia/Kolkata')->startOfDay(),
            'metadata' => [
                'open_at' => Carbon::create(2025, 9, 29, 21, 30, 0, 'Asia/Kolkata')->toISOString(),
                'close_at' => Carbon::create(2025, 9, 30, 0, 30, 0, 'Asia/Kolkata')->toISOString(),
                'session_date' => '2025-09-29',
            ],
        ]);

        // Mark all students
        AttendanceMark::create([
            'tenant_id' => $tenant->id,
            'attendance_session_id' => $session->id,
            'student_id' => $studentRecords[0]->id,
            'status' => 'present',
            'marked_by' => $warden->id,
            'marked_at' => now(),
        ]);

        AttendanceMark::create([
            'tenant_id' => $tenant->id,
            'attendance_session_id' => $session->id,
            'student_id' => $studentRecords[1]->id,
            'status' => 'present',
            'marked_by' => $warden->id,
            'marked_at' => now(),
        ]);

        AttendanceMark::create([
            'tenant_id' => $tenant->id,
            'attendance_session_id' => $session->id,
            'student_id' => $studentRecords[2]->id,
            'status' => 'absent',
            'marked_by' => $warden->id,
            'marked_at' => now(),
        ]);

        Sanctum::actingAs($warden, ['*']);

        $response = $this->postJson("/api/v1/attendance/sessions/{$session->id}/rooms/{$room->id}/submit");

        $response->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_warden_cannot_submit_room_with_unmarked_students(): void
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

        $students = collect();
        for ($i = 0; $i < 3; $i++) {
            $students->push(User::factory()->create([
                'tenant_id' => $tenant->id,
                'kind' => 'Student',
            ]));
        }
        $roomData = AttendanceTestHelpers::createRoomWithBedsAndAllocations($tenant, $hostel, $room, $students);
        $studentRecords = $roomData['students'];

        Carbon::setTestNow(Carbon::create(2025, 9, 29, 21, 40, 0, 'Asia/Kolkata'));

        $session = AttendanceSession::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'kind' => 'night_check',
            'status' => 'open',
            'scheduled_at' => Carbon::now('Asia/Kolkata')->startOfDay(),
            'metadata' => [
                'open_at' => Carbon::create(2025, 9, 29, 21, 30, 0, 'Asia/Kolkata')->toISOString(),
                'close_at' => Carbon::create(2025, 9, 30, 0, 30, 0, 'Asia/Kolkata')->toISOString(),
                'session_date' => '2025-09-29',
            ],
        ]);

        // Mark only two students, leave one unmarked
        AttendanceMark::create([
            'tenant_id' => $tenant->id,
            'attendance_session_id' => $session->id,
            'student_id' => $studentRecords[0]->id,
            'status' => 'present',
            'marked_by' => $warden->id,
            'marked_at' => now(),
        ]);

        AttendanceMark::create([
            'tenant_id' => $tenant->id,
            'attendance_session_id' => $session->id,
            'student_id' => $studentRecords[1]->id,
            'status' => 'absent',
            'marked_by' => $warden->id,
            'marked_at' => now(),
        ]);

        Sanctum::actingAs($warden, ['*']);

        $response = $this->postJson("/api/v1/attendance/sessions/{$session->id}/rooms/{$room->id}/submit");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['room' => 'All non-leave students must be marked before submit.']);
    }

    public function test_warden_can_submit_room_with_leave_students(): void
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

        $students = collect();
        for ($i = 0; $i < 3; $i++) {
            $students->push(User::factory()->create([
                'tenant_id' => $tenant->id,
                'kind' => 'Student',
            ]));
        }
        $roomData = AttendanceTestHelpers::createRoomWithBedsAndAllocations($tenant, $hostel, $room, $students);
        $studentRecords = $roomData['students'];

        Carbon::setTestNow(Carbon::create(2025, 9, 29, 21, 40, 0, 'Asia/Kolkata'));

        $session = AttendanceSession::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'kind' => 'night_check',
            'status' => 'open',
            'scheduled_at' => Carbon::now('Asia/Kolkata')->startOfDay(),
            'metadata' => [
                'open_at' => Carbon::create(2025, 9, 29, 21, 30, 0, 'Asia/Kolkata')->toISOString(),
                'close_at' => Carbon::create(2025, 9, 30, 0, 30, 0, 'Asia/Kolkata')->toISOString(),
                'session_date' => '2025-09-29',
            ],
        ]);

        // Create approved outpass for one student (leave)
        OutPass::factory()->create([
            'tenant_id' => $tenant->id,
            'student_id' => $studentRecords[1]->id, // Use Student ID, not User ID
            'status' => 'approved',
            'requested_at' => Carbon::parse($session->metadata['open_at']),
            'valid_until' => Carbon::parse($session->metadata['close_at']),
        ]);

        // Mark non-leave students
        AttendanceMark::create([
            'tenant_id' => $tenant->id,
            'attendance_session_id' => $session->id,
            'student_id' => $studentRecords[0]->id,
            'status' => 'present',
            'marked_by' => $warden->id,
            'marked_at' => now(),
        ]);

        AttendanceMark::create([
            'tenant_id' => $tenant->id,
            'attendance_session_id' => $session->id,
            'student_id' => $studentRecords[2]->id,
            'status' => 'absent',
            'marked_by' => $warden->id,
            'marked_at' => now(),
        ]);

        Sanctum::actingAs($warden, ['*']);

        $response = $this->postJson("/api/v1/attendance/sessions/{$session->id}/rooms/{$room->id}/submit");

        $response->assertOk()
            ->assertJson(['ok' => true]);
    }
}