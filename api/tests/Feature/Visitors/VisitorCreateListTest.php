<?php

namespace Tests\Feature\Visitors;

use App\Domain\Visitors\Models\GuestVisit;
use App\Models\Hostel;
use App\Models\Room;
use App\Models\RoomAllocation;
use App\Models\RoomBed;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VisitorCreateListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Student', 'guard_name' => 'sanctum']);
    }

    public function test_student_can_create_visitor_pre_registration(): void
    {
        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $studentUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'kind' => 'Student',
        ]);
        $studentUser->assignRole('Student');

        $student = Student::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'user_id' => $studentUser->id,
        ]);

        // Create active room allocation
        $room = Room::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
        ]);
        $bed = RoomBed::create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'room_id' => $room->id,
            'code' => 'A',
            'status' => 'available',
        ]);
        RoomAllocation::create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'student_id' => $student->id,
            'room_bed_id' => $bed->id,
            'is_active' => true,
            'effective_from' => now(),
        ]);

        Sanctum::actingAs($studentUser, ['*']);

        $response = $this->postJson('/api/v1/visitors', [
            'name' => 'John Doe',
            'phone' => '9876543210',
            'whom_to_meet' => 'My friend from college',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'status'])
            ->assertJson([
                'status' => 'pre_registered',
            ]);

        $this->assertDatabaseHas('guest_visits', [
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'student_id' => $student->id,
            'name' => 'John Doe',
            'phone' => '9876543210',
            'whom_to_meet' => 'My friend from college',
            'status' => 'pre_registered',
            'created_by_user_id' => $studentUser->id,
        ]);
    }

    public function test_student_can_list_own_visitors_today(): void
    {
        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $studentUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'kind' => 'Student',
        ]);
        $studentUser->assignRole('Student');

        $student = Student::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'user_id' => $studentUser->id,
        ]);

        // Create visitor for today
        $visit = GuestVisit::create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'student_id' => $student->id,
            'name' => 'Jane Smith',
            'phone' => '9876543211',
            'whom_to_meet' => 'Family visit',
            'visit_date' => today(),
            'status' => GuestVisit::STATUS_PRE_REGISTERED,
            'created_by_user_id' => $studentUser->id,
        ]);

        // Create visitor for yesterday (should not appear)
        GuestVisit::create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'student_id' => $student->id,
            'name' => 'Old Visitor',
            'phone' => '9876543212',
            'whom_to_meet' => 'Yesterday',
            'visit_date' => Carbon::yesterday(),
            'status' => GuestVisit::STATUS_PRE_REGISTERED,
            'created_by_user_id' => $studentUser->id,
        ]);

        Sanctum::actingAs($studentUser, ['*']);

        $response = $this->getJson('/api/v1/visitors/mine/today');

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment([
                'id' => $visit->id,
                'name' => 'Jane Smith',
                'phone' => '9876543211',
            ]);
    }

    public function test_student_without_allocation_can_still_create_with_hostel_id(): void
    {
        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $studentUser = User::factory()->create([
            'tenant_id' => $tenant->id,
            'kind' => 'Student',
        ]);
        $studentUser->assignRole('Student');

        $student = Student::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'user_id' => $studentUser->id,
        ]);

        Sanctum::actingAs($studentUser, ['*']);

        $response = $this->postJson('/api/v1/visitors', [
            'name' => 'John Doe',
            'phone' => '9876543210',
            'whom_to_meet' => 'Friend',
            'hostel_id' => $hostel->id,
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('guest_visits', [
            'hostel_id' => $hostel->id,
            'student_id' => $student->id,
            'name' => 'John Doe',
        ]);
    }
}

