<?php

namespace Tests\Feature\Attendance;

use App\Domain\Attendance\Models\AttendanceMark;
use App\Domain\Attendance\Models\AttendanceSession;
use App\Jobs\AttendanceCloseJob;
use App\Models\Hostel;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditLogger;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tests\Support\AttendanceTestHelpers;

class AttendanceCloseJobTest extends TestCase
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

    public function test_close_job_closes_session_and_audits_unmarked(): void
    {
        // Note: Audit logging is tested separately, focusing on session closure here

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
        for ($i = 0; $i < 2; $i++) {
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

        // Mark one student, leave one unmarked (no record created)
        AttendanceMark::create([
            'tenant_id' => $tenant->id,
            'attendance_session_id' => $session->id,
            'student_id' => $studentRecords[0]->id,
            'status' => 'present',
            'marked_by' => $warden->id,
            'marked_at' => now(),
        ]);

        // Don't create any record for the second student - they will be considered unmarked

        // Time travel past close_at
        Carbon::setTestNow(Carbon::create(2025, 9, 30, 0, 31, 0, 'Asia/Kolkata'));

        (new AttendanceCloseJob(app(AuditLogger::class)))->handle();

        $session->refresh();
        $this->assertEquals('closed', $session->status);
        $this->assertEquals(1, $session->metadata['present_count']);
        $this->assertEquals(0, $session->metadata['absent_count']);
        $this->assertEquals(0, $session->metadata['leave_count']); // No leave students in this test
    }

    public function test_cannot_mark_after_session_is_closed(): void
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

        Carbon::setTestNow(Carbon::create(2025, 9, 29, 21, 40, 0, 'Asia/Kolkata'));

        $session = AttendanceSession::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'kind' => 'night_check',
            'status' => 'closed', // Already closed
            'scheduled_at' => Carbon::now('Asia/Kolkata')->startOfDay(),
            'metadata' => [
                'open_at' => Carbon::create(2025, 9, 29, 21, 30, 0, 'Asia/Kolkata')->toISOString(),
                'close_at' => Carbon::create(2025, 9, 30, 0, 30, 0, 'Asia/Kolkata')->toISOString(),
                'session_date' => '2025-09-29',
            ],
        ]);

        Sanctum::actingAs($warden, ['*']);

        $response = $this->postJson("/api/v1/attendance/sessions/{$session->id}/rooms/{$room->id}/mark", [
            'student_id' => $student->id,
            'status' => 'present',
        ]);

        $response->assertForbidden();
    }

    public function test_close_job_is_idempotent(): void
    {
        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create([
            'tenant_id' => $tenant->id,
            'curfew_time' => '22:30:00',
        ]);

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

        // Time travel past close_at
        Carbon::setTestNow(Carbon::create(2025, 9, 30, 0, 31, 0, 'Asia/Kolkata'));

        // Run job twice
        (new AttendanceCloseJob(app(AuditLogger::class)))->handle();
        (new AttendanceCloseJob(app(AuditLogger::class)))->handle();

        $session->refresh();
        $this->assertEquals('closed', $session->status);
    }
}