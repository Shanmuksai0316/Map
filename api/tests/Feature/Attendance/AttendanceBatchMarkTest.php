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

class AttendanceBatchMarkTest extends TestCase
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

    public function test_warden_can_batch_mark_students(): void
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
        for ($i = 0; $i < 4; $i++) {
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
            'student_id' => $studentRecords[2]->id, // Use Student ID, not User ID
            'status' => 'approved',
            'requested_at' => Carbon::parse($session->metadata['open_at']),
            'valid_until' => Carbon::parse($session->metadata['close_at']),
        ]);

        Sanctum::actingAs($warden, ['*']);

        $response = $this->postJson("/api/v1/attendance/sessions/{$session->id}/rooms/{$room->id}/marks/batch", [
            'items' => [
                ['student_id' => $students[0]->id, 'status' => 'present'],
                ['student_id' => $students[1]->id, 'status' => 'present'],
                ['student_id' => $students[2]->id, 'status' => 'absent', 'comment' => 'Batch absent'], // Should be skipped (on leave)
                ['student_id' => $students[3]->id, 'status' => 'present'],
            ],
        ]);

        $response->assertOk()
            ->assertJson([
                'updated' => 3,
                'skipped' => 1,
                'summary' => [
                    'total' => 4,
                    'present' => 3,
                    'absent' => 0,
                    'leave' => 1,
                    'unmarked' => 0,
                ],
            ]);

        $this->assertDatabaseHas('attendance_logs', [
            'attendance_session_id' => $session->id,
            'student_id' => $studentRecords[1]->id,
            'status' => 'present',
        ]);
        $this->assertDatabaseHas('attendance_logs', [
            'attendance_session_id' => $session->id,
            'student_id' => $studentRecords[3]->id,
            'status' => 'present',
        ]);
        $this->assertDatabaseMissing('attendance_logs', [
            'attendance_session_id' => $session->id,
            'student_id' => $studentRecords[2]->id,
            'status' => 'absent',
        ]);
    }

    public function test_batch_mark_validates_max_items(): void
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

        Sanctum::actingAs($warden, ['*']);

        // Create 101 items (exceeds max of 100)
        $items = [];
        for ($i = 0; $i < 101; $i++) {
            $items[] = ['student_id' => 1, 'status' => 'present'];
        }

        $response = $this->postJson("/api/v1/attendance/sessions/{$session->id}/rooms/{$room->id}/marks/batch", [
            'items' => $items,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }
}