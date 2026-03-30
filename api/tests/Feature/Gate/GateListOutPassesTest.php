<?php

namespace Tests\Feature\Gate;

use App\Models\Domain\OutPass\OutPass;
use App\Models\Hostel;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GateListOutPassesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::firstOrCreate(['name' => 'Guard', 'guard_name' => 'sanctum']);
        Role::firstOrCreate(['name' => 'Student', 'guard_name' => 'sanctum']);
    }

    public function test_guard_can_list_todays_approved_outpasses(): void
    {
        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $guard = User::factory()->create([
            'tenant_id' => $tenant->id,
            'kind' => 'Guard',
        ]);
        $guard->assignRole('Guard');

        $student = Student::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
        ]);

        // Create today's approved outpass
        $outpass = OutPass::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'student_id' => $student->id,
            'status' => 'approved',
            'requested_at' => Carbon::today()->addHours(10),
            'valid_until' => Carbon::today()->addHours(18),
        ]);

        // Create yesterday's outpass (should not appear)
        OutPass::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'student_id' => $student->id,
            'status' => 'approved',
            'requested_at' => Carbon::yesterday()->addHours(10),
            'valid_until' => Carbon::yesterday()->addHours(18),
        ]);

        // Create pending outpass (should not appear)
        OutPass::factory()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'student_id' => $student->id,
            'status' => 'pending',
            'requested_at' => Carbon::today()->addHours(10),
            'valid_until' => Carbon::today()->addHours(18),
        ]);

        Sanctum::actingAs($guard, ['*']);

        $response = $this->getJson('/api/v1/gate/outpasses/today');

        $response->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment([
                'id' => $outpass->id,
                'student_id' => $student->id,
                'status' => 'approved',
            ]);
    }

    public function test_student_cannot_list_outpasses(): void
    {
        $tenant = Tenant::factory()->create();
        $student = User::factory()->create([
            'tenant_id' => $tenant->id,
            'kind' => 'Student',
        ]);
        $student->assignRole('Student');

        Sanctum::actingAs($student, ['*']);

        $response = $this->getJson('/api/v1/gate/outpasses/today');

        $response->assertForbidden();
    }
}

