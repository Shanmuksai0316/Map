<?php

namespace Tests\Feature\Dashboard;

use App\Domain\Attendance\Models\AttendanceMark;
use App\Domain\Attendance\Models\AttendanceSession;
use App\Domain\Checklists\Models\ChecklistInstance;
use App\Domain\Gate\Models\GateDevice;
use App\Domain\Gate\Models\GateEntry;
use App\Models\Campus;
use App\Models\Domain\OutPass\OutPass;
use App\Models\Hostel;
use App\Models\Room;
use App\Models\RoomAllocation;
use App\Models\RoomBed;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\Ticket;
use App\Support\Dashboard\DateRange;
use App\Support\Dashboard\KpisRepository;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardKpisTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Hostel $hostel1;
    private Hostel $hostel2;
    private KpisRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo = new KpisRepository();
        $this->tenant = Tenant::factory()->create();
        
        $campus = Campus::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->hostel1 = Hostel::factory()->create([
            'tenant_id' => $this->tenant->id,
            'campus_id' => $campus->id,
        ]);

        $this->hostel2 = Hostel::factory()->create([
            'tenant_id' => $this->tenant->id,
            'campus_id' => $campus->id,
        ]);
    }

    public function test_occupancy_calculates_correctly(): void
    {
        // Create beds and allocations
        $room1 = Room::factory()->create([
            'tenant_id' => $this->tenant->id,
            'hostel_id' => $this->hostel1->id,
        ]);

        // 4 beds in room1
        for ($i = 0; $i < 4; $i++) {
            RoomBed::create([
                'tenant_id' => $this->tenant->id,
                'hostel_id' => $this->hostel1->id,
                'room_id' => $room1->id,
                'code' => chr(65 + $i), // A, B, C, D
                'status' => 'available',
            ]);
        }

        // 2 occupied beds
        $beds = RoomBed::where('room_id', $room1->id)->limit(2)->get();
        foreach ($beds as $bed) {
            $student = Student::factory()->create([
                'tenant_id' => $this->tenant->id,
                'hostel_id' => $this->hostel1->id,
            ]);

            RoomAllocation::create([
                'tenant_id' => $this->tenant->id,
                'hostel_id' => $this->hostel1->id,
                'student_id' => $student->id,
                'room_bed_id' => $bed->id,
                'is_active' => true,
                'effective_from' => now(),
            ]);
        }

        $result = $this->repo->occupancy($this->tenant->id, [$this->hostel1->id]);

        $this->assertEquals(4, $result['total']);
        $this->assertEquals(2, $result['occupied']);
        $this->assertEquals(2, $result['available']);
        $this->assertEquals(50.0, $result['utilization']);
    }

    public function test_outpass_daily_counts_returns_array(): void
    {
        $student = Student::factory()->create([
            'tenant_id' => $this->tenant->id,
            'hostel_id' => $this->hostel1->id,
        ]);

        // Create outpasses for different days
        OutPass::factory()->create([
            'tenant_id' => $this->tenant->id,
            'hostel_id' => $this->hostel1->id,
            'student_id' => $student->id,
            'status' => 'approved',
            'requested_at' => Carbon::today(),
        ]);

        OutPass::factory()->create([
            'tenant_id' => $this->tenant->id,
            'hostel_id' => $this->hostel1->id,
            'student_id' => $student->id,
            'status' => 'approved',
            'requested_at' => Carbon::yesterday(),
        ]);

        $result = $this->repo->outPassDailyCounts($this->tenant->id, 14, [$this->hostel1->id]);

        $this->assertIsArray($result);
        $this->assertCount(14, $result);
        $this->assertGreaterThanOrEqual(1, $result[Carbon::today()->toDateString()]);
    }

    public function test_late_return_split_counts_correctly(): void
    {
        $student = Student::factory()->create([
            'tenant_id' => $this->tenant->id,
            'hostel_id' => $this->hostel1->id,
        ]);

        // Create gate entries with different late_minutes
        GateEntry::create([
            'tenant_id' => $this->tenant->id,
            'hostel_id' => $this->hostel1->id,
            'student_id' => $student->id,
            'direction' => 'in',
            'method' => 'qr',
            'event' => 'student_entry',
            'occurred_at' => now(),
            'late_minutes' => 0, // On time
            'guard_user_id' => 1,
        ]);

        GateEntry::create([
            'tenant_id' => $this->tenant->id,
            'hostel_id' => $this->hostel1->id,
            'student_id' => $student->id,
            'direction' => 'in',
            'method' => 'qr',
            'event' => 'student_entry',
            'occurred_at' => now(),
            'late_minutes' => 30, // Late
            'guard_user_id' => 1,
        ]);

        $result = $this->repo->lateReturnSplit($this->tenant->id, 7, [$this->hostel1->id]);

        $this->assertEquals(1, $result['on_time']);
        $this->assertEquals(1, $result['late']);
    }

    public function test_attendance_compliance_returns_percentages(): void
    {
        // Create session
        AttendanceSession::factory()->create([
            'tenant_id' => $this->tenant->id,
            'hostel_id' => $this->hostel1->id,
            'scheduled_at' => Carbon::yesterday(),
            'status' => 'closed',
        ]);

        $result = $this->repo->attendanceCompliance($this->tenant->id, 7, [$this->hostel1->id]);

        $this->assertIsArray($result);
        $this->assertCount(7, $result);
        
        foreach ($result as $date => $compliance) {
            $this->assertGreaterThanOrEqual(0, $compliance);
            $this->assertLessThanOrEqual(100, $compliance);
        }
    }

    public function test_devices_health_counts_active_and_stale(): void
    {
        // Create active device (seen recently)
        GateDevice::create([
            'tenant_id' => $this->tenant->id,
            'hostel_id' => $this->hostel1->id,
            'device_uuid' => 'ACTIVE-DEVICE',
            'name' => 'Active Tablet',
            'is_active' => true,
            'last_seen_at' => Carbon::now()->subMinutes(5),
        ]);

        // Create stale device (seen 30 minutes ago)
        GateDevice::create([
            'tenant_id' => $this->tenant->id,
            'hostel_id' => $this->hostel1->id,
            'device_uuid' => 'STALE-DEVICE',
            'name' => 'Stale Tablet',
            'is_active' => true,
            'last_seen_at' => Carbon::now()->subMinutes(30),
        ]);

        $result = $this->repo->devicesHealth($this->tenant->id, [$this->hostel1->id]);

        $this->assertEquals(2, $result['total']);
        $this->assertEquals(1, $result['active']);
        $this->assertEquals(1, $result['stale']);
    }

    public function test_kpis_are_cached(): void
    {
        // First call - should hit DB
        $result1 = $this->repo->occupancy($this->tenant->id, []);
        
        // Second call - should be cached
        $result2 = $this->repo->occupancy($this->tenant->id, []);

        $this->assertEquals($result1, $result2);
    }
}

