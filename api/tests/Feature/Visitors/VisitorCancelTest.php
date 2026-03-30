<?php

namespace Tests\Feature\Visitors;

use App\Domain\Visitors\Models\GuestVisit;
use App\Models\Hostel;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VisitorCancelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Student', 'guard_name' => 'sanctum']);
    }

    public function test_student_can_cancel_own_pre_registered_visit(): void
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

        Sanctum::actingAs($studentUser, ['*']);

        $response = $this->deleteJson("/api/v1/visitors/{$visit->id}");

        $response->assertOk()
            ->assertJson([
                'message' => 'Visitor pre-registration cancelled',
            ]);

        $this->assertDatabaseHas('guest_visits', [
            'id' => $visit->id,
            'status' => 'denied',
            'denied_by_user_id' => $studentUser->id,
        ]);
    }

    public function test_student_cannot_cancel_already_allowed_visit(): void
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

        $visit = GuestVisit::create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'student_id' => $student->id,
            'name' => 'Jane Smith',
            'phone' => '9876543211',
            'whom_to_meet' => 'Family visit',
            'visit_date' => today(),
            'status' => GuestVisit::STATUS_ALLOWED,
            'created_by_user_id' => $studentUser->id,
            'allowed_by_user_id' => $studentUser->id,
            'allowed_at' => now(),
        ]);

        Sanctum::actingAs($studentUser, ['*']);

        $response = $this->deleteJson("/api/v1/visitors/{$visit->id}");

        $response->assertForbidden();
    }

    public function test_student_cannot_cancel_past_visit(): void
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

        $visit = GuestVisit::create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'student_id' => $student->id,
            'name' => 'Jane Smith',
            'phone' => '9876543211',
            'whom_to_meet' => 'Family visit',
            'visit_date' => Carbon::yesterday(),
            'status' => GuestVisit::STATUS_PRE_REGISTERED,
            'created_by_user_id' => $studentUser->id,
        ]);

        Sanctum::actingAs($studentUser, ['*']);

        $response = $this->deleteJson("/api/v1/visitors/{$visit->id}");

        $response->assertForbidden();
    }

    public function test_student_cannot_cancel_other_students_visit(): void
    {
        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $student1User = User::factory()->create([
            'tenant_id' => $tenant->id,
            'kind' => 'Student',
        ]);
        $student1User->assignRole('Student');

        $student1 = Student::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'user_id' => $student1User->id,
        ]);

        $student2User = User::factory()->create([
            'tenant_id' => $tenant->id,
            'kind' => 'Student',
        ]);
        $student2User->assignRole('Student');

        $student2 = Student::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'user_id' => $student2User->id,
        ]);

        $visit = GuestVisit::create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'student_id' => $student2->id, // Student 2's visit
            'name' => 'Jane Smith',
            'phone' => '9876543211',
            'whom_to_meet' => 'Family visit',
            'visit_date' => today(),
            'status' => GuestVisit::STATUS_PRE_REGISTERED,
            'created_by_user_id' => $student2User->id,
        ]);

        // Try to cancel as Student 1
        Sanctum::actingAs($student1User, ['*']);

        $response = $this->deleteJson("/api/v1/visitors/{$visit->id}");

        $response->assertForbidden();
    }
}

