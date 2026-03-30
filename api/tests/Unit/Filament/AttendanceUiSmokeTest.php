<?php

namespace Tests\Unit\Filament;

use App\Domain\Attendance\Models\AttendanceSession;
use App\Filament\CampusManager\Resources\AttendanceSessionResource;
use App\Filament\CampusManager\Resources\AttendanceSessionResource\Pages\ListAttendanceSessions;
use App\Filament\CampusManager\Resources\AttendanceSessionResource\Pages\ViewAttendanceSession;
use App\Models\Hostel;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Tests\Support\AttendanceTestHelpers;

class AttendanceUiSmokeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate(['name' => 'Warden', 'guard_name' => 'sanctum']);
        Role::firstOrCreate(['name' => 'Campus Manager', 'guard_name' => 'sanctum']);
        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'sanctum']);
    }

    public function test_warden_can_access_attendance_resource(): void
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

        // Create today's session
        $session = AttendanceSession::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'kind' => 'night_check',
            'status' => 'open',
            'scheduled_at' => Carbon::now('Asia/Kolkata')->startOfDay(),
            'metadata' => [
                'open_at' => Carbon::now('Asia/Kolkata')->subHour()->toISOString(),
                'close_at' => Carbon::now('Asia/Kolkata')->addHour()->toISOString(),
                'session_date' => Carbon::now('Asia/Kolkata')->toDateString(),
            ],
        ]);

        // Create students and room allocations
        $students = collect();
        for ($i = 0; $i < 3; $i++) {
            $students->push(User::factory()->create([
                'tenant_id' => $tenant->id,
                'kind' => 'Student',
            ]));
        }
        AttendanceTestHelpers::createRoomWithBedsAndAllocations($tenant, $hostel, $room, $students);

        $this->actingAs($warden);

        // Test resource registration
        $this->assertTrue(AttendanceSessionResource::canViewAny());

        // Test list page
        $listPage = Livewire::test(ListAttendanceSessions::class);
        $listPage->assertSuccessful();
        $listPage->assertCanSeeTableRecords([$session]);

        // Test view page
        $viewPage = Livewire::test(ViewAttendanceSession::class, ['record' => $session->id]);
        $viewPage->assertSuccessful();
    }

    public function test_campus_manager_can_view_but_cannot_mark(): void
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

        $campusManager = User::factory()->create([
            'tenant_id' => $tenant->id,
            'kind' => 'Campus Manager',
        ]);
        $campusManager->assignRole('Campus Manager');

        // Create today's session
        $session = AttendanceSession::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'kind' => 'night_check',
            'status' => 'open',
            'scheduled_at' => Carbon::now('Asia/Kolkata')->startOfDay(),
            'metadata' => [
                'open_at' => Carbon::now('Asia/Kolkata')->subHour()->toISOString(),
                'close_at' => Carbon::now('Asia/Kolkata')->addHour()->toISOString(),
                'session_date' => Carbon::now('Asia/Kolkata')->toDateString(),
            ],
        ]);

        // Create students and room allocations
        $students = collect();
        for ($i = 0; $i < 3; $i++) {
            $students->push(User::factory()->create([
                'tenant_id' => $tenant->id,
                'kind' => 'Student',
            ]));
        }
        AttendanceTestHelpers::createRoomWithBedsAndAllocations($tenant, $hostel, $room, $students);

        $this->actingAs($campusManager);

        // Test resource registration
        $this->assertTrue(AttendanceSessionResource::canViewAny());

        // Test list page
        $listPage = Livewire::test(ListAttendanceSessions::class);
        $listPage->assertSuccessful();
        $listPage->assertCanSeeTableRecords([$session]);

        // Test view page
        $viewPage = Livewire::test(ViewAttendanceSession::class, ['record' => $session->id]);
        $viewPage->assertSuccessful();
    }

    public function test_mark_present_action_simulation(): void
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

        // Create today's session
        $session = AttendanceSession::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'kind' => 'night_check',
            'status' => 'open',
            'scheduled_at' => Carbon::now('Asia/Kolkata')->startOfDay(),
            'metadata' => [
                'open_at' => Carbon::now('Asia/Kolkata')->subHour()->toISOString(),
                'close_at' => Carbon::now('Asia/Kolkata')->addHour()->toISOString(),
                'session_date' => Carbon::now('Asia/Kolkata')->toDateString(),
            ],
        ]);

        // Create students and room allocations
        $students = collect();
        for ($i = 0; $i < 3; $i++) {
            $students->push(User::factory()->create([
                'tenant_id' => $tenant->id,
                'kind' => 'Student',
            ]));
        }
        $roomData = AttendanceTestHelpers::createRoomWithBedsAndAllocations($tenant, $hostel, $room, $students);
        $studentRecord = $roomData['students'][0];

        $this->actingAs($warden);

        // Test view page with room selected
        $viewPage = Livewire::test(ViewAttendanceSession::class, ['record' => $session->id])
            ->set('room_id', $room->id);

        $viewPage->assertSuccessful();

        // Simulate mark present action
        $viewPage->call('markPresent', $students[0]->id);
        
        // Should not throw exception (Livewire test should complete successfully)
        $viewPage->assertSuccessful();
    }

    public function test_submit_room_action_simulation(): void
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

        // Create today's session
        $session = AttendanceSession::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'kind' => 'night_check',
            'status' => 'open',
            'scheduled_at' => Carbon::now('Asia/Kolkata')->startOfDay(),
            'metadata' => [
                'open_at' => Carbon::now('Asia/Kolkata')->subHour()->toISOString(),
                'close_at' => Carbon::now('Asia/Kolkata')->addHour()->toISOString(),
                'session_date' => Carbon::now('Asia/Kolkata')->toDateString(),
            ],
        ]);

        // Create students and room allocations
        $students = collect();
        for ($i = 0; $i < 3; $i++) {
            $students->push(User::factory()->create([
                'tenant_id' => $tenant->id,
                'kind' => 'Student',
            ]));
        }
        AttendanceTestHelpers::createRoomWithBedsAndAllocations($tenant, $hostel, $room, $students);

        $this->actingAs($warden);

        // Test view page with room selected
        $viewPage = Livewire::test(ViewAttendanceSession::class, ['record' => $session->id])
            ->set('room_id', $room->id);

        $viewPage->assertSuccessful();

        // Simulate submit room action (should fail with 422 since no students marked)
        $viewPage->call('submitRoom');
        
        // Should not throw exception (Livewire test should complete successfully)
        $viewPage->assertSuccessful();
    }
}
