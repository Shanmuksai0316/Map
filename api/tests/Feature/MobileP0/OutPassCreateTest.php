<?php

namespace Tests\Feature\MobileP0;

use App\Models\Domain\OutPass\OutPass;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OutPassCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_create_outpass(): void
    {
        // Create a tenant first
        $tenant = \App\Models\Tenant::factory()->create();
        
        // Create a student user
        $student = User::factory()->create([
            'kind' => 'Student',
            'tenant_id' => $tenant->id,
        ]);

        // Create a hostel first
        $hostel = \App\Models\Hostel::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
        
        // Create student record
        $student->student()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'student_uid' => 'TEST-' . rand(1000, 9999),
            'map_student_id' => 'MAP-' . rand(10000, 99999),
        ]);

        // Authenticate as student
        Sanctum::actingAs($student);

        // Create out-pass request
        $response = $this->postJson('/api/v1/outpasses', [
            'reason' => 'normal',
            'overnight' => false,
            'note' => 'Test out-pass creation',
        ]);

        // Assert response
        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'tenant_id',
                    'student_id',
                    'hostel_id',
                    'reason',
                    'overnight',
                    'status',
                    'requested_at',
                    'valid_until',
                    'note',
                    'histories',
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJson([
                'data' => [
                    'tenant_id' => 1,
                    'student_id' => $student->student->id,
                    'hostel_id' => 1,
                    'reason' => 'normal',
                    'overnight' => false,
                    'status' => 'pending',
                    'note' => 'Test out-pass creation',
                ],
            ]);

        // Assert out-pass was created in database
        $this->assertDatabaseHas('out_passes', [
            'tenant_id' => 1,
            'student_id' => $student->student->id,
            'hostel_id' => 1,
            'reason' => 'normal',
            'overnight' => false,
            'status' => 'pending',
        ]);
    }

    public function test_outpass_creation_auto_fills_fields(): void
    {
        // Create a tenant first
        $tenant = \App\Models\Tenant::factory()->create();
        
        // Create a student user
        $student = User::factory()->create([
            'kind' => 'Student',
            'tenant_id' => $tenant->id,
        ]);

        // Create a hostel first
        $hostel = \App\Models\Hostel::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
        
        // Create student record
        $student->student()->create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'student_uid' => 'TEST-' . rand(1000, 9999),
            'map_student_id' => 'MAP-' . rand(10000, 99999),
        ]);

        // Authenticate as student
        Sanctum::actingAs($student);

        // Create out-pass request with minimal data
        $response = $this->postJson('/api/v1/outpasses', [
            'reason' => 'leave',
            'overnight' => true,
        ]);

        // Assert response
        $response->assertStatus(201)
            ->assertJson([
                'data' => [
                    'tenant_id' => (string) $tenant->id,
                    'student_id' => (string) $student->student->id,
                    'hostel_id' => (string) $hostel->id, // Auto-filled from student's hostel
                    'reason' => 'leave',
                    'overnight' => true,
                    'status' => 'pending',
                ],
            ]);

        // Assert valid_until is set (8 hours from now)
        $outpass = OutPass::find($response->json('data.id'));
        $this->assertNotNull($outpass->valid_until);
        $this->assertGreaterThan(now('Asia/Kolkata'), $outpass->valid_until);
    }
}
