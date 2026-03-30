<?php

namespace Tests\Feature\Gate;

use App\Models\GateDutyHandover;
use App\Models\Hostel;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GateDutyHandoverTest extends TestCase
{
    use RefreshDatabase;

    private User $guard;
    private Tenant $tenant;
    private Hostel $hostel;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();

        $this->hostel = Hostel::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->guard = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'kind' => 'Guard'
        ]);

        $this->guard->assignRole('Guard');
    }

    public function test_guard_can_create_duty_handover(): void
    {
        Sanctum::actingAs($this->guard);

        $shiftStart = now()->addHour();
        $shiftEnd = now()->addHours(8);

        $response = $this->postJson('/api/v1/gate/duty-handovers', [
            'hostel_id' => $this->hostel->id,
            'shift_start' => $shiftStart->toISOString(),
            'shift_end' => $shiftEnd->toISOString(),
            'notes' => 'Morning shift handover',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'handover' => [
                    'id',
                    'guardUser' => ['id', 'name'],
                    'hostel' => ['id', 'name'],
                    'shift_start',
                    'shift_end',
                    'status',
                ]
            ]);

        $this->assertDatabaseHas('gate_duty_handovers', [
            'tenant_id' => $this->tenant->id,
            'hostel_id' => $this->hostel->id,
            'guard_id' => $this->guard->id,
            'status' => 'active',
            'notes' => 'Morning shift handover',
        ]);
    }

    public function test_guard_can_complete_duty_handover(): void
    {
        Sanctum::actingAs($this->guard);

        $handover = GateDutyHandover::factory()->create([
            'tenant_id' => $this->tenant->id,
            'hostel_id' => $this->hostel->id,
            'guard_id' => $this->guard->id,
            'status' => 'active',
        ]);

        $response = $this->postJson("/api/v1/gate/duty-handovers/{$handover->id}/complete", [
            'incidents_count' => 2,
            'entries_processed' => 45,
            'issues_reported' => 'Minor equipment malfunction',
            'completion_notes' => 'Shift completed successfully',
        ]);

        $response->assertStatus(200);

        $handover->refresh();
        $this->assertEquals('completed', $handover->status);
        $this->assertEquals(2, $handover->incidents_count);
        $this->assertEquals(45, $handover->entries_processed);
        $this->assertStringContains('Shift completed successfully', $handover->notes);
    }

    public function test_guard_can_list_today_duty_handovers(): void
    {
        Sanctum::actingAs($this->guard);

        // Create today's handover
        GateDutyHandover::factory()->create([
            'tenant_id' => $this->tenant->id,
            'hostel_id' => $this->hostel->id,
            'guard_id' => $this->guard->id,
            'shift_start' => now()->startOfDay()->addHours(8),
            'shift_end' => now()->startOfDay()->addHours(16),
        ]);

        $response = $this->getJson('/api/v1/gate/duty-handovers/today');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'guardUser' => ['id', 'name'],
                        'hostel' => ['id', 'name'],
                        'shift_start',
                        'shift_end',
                        'status',
                        'is_active',
                        'is_today',
                    ]
                ]
            ]);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals($this->guard->id, $data[0]['guard']['id']);
    }

    public function test_guard_cannot_complete_another_guards_handover(): void
    {
        Sanctum::actingAs($this->guard);

        $otherGuard = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'kind' => 'Guard'
        ]);
        $otherGuard->assignRole('Guard');

        $handover = GateDutyHandover::factory()->create([
            'tenant_id' => $this->tenant->id,
            'hostel_id' => $this->hostel->id,
            'guard_id' => $otherGuard->id,
            'status' => 'active',
        ]);

        $response = $this->postJson("/api/v1/gate/duty-handovers/{$handover->id}/complete", [
            'incidents_count' => 0,
            'entries_processed' => 20,
        ]);

        $response->assertStatus(403);
    }

    public function test_manager_can_complete_any_handover(): void
    {
        Sanctum::actingAs($this->guard);

        $manager = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'kind' => 'CampusManager'
        ]);
        $manager->assignRole('Campus Manager');

        $handover = GateDutyHandover::factory()->create([
            'tenant_id' => $this->tenant->id,
            'hostel_id' => $this->hostel->id,
            'guard_id' => $this->guard->id,
            'status' => 'active',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->postJson("/api/v1/gate/duty-handovers/{$handover->id}/complete", [
            'incidents_count' => 1,
            'entries_processed' => 30,
            'completion_notes' => 'Manager completed handover',
        ]);

        $response->assertStatus(200);

        $handover->refresh();
        $this->assertEquals('completed', $handover->status);
        $this->assertStringContains('Manager completed handover', $handover->notes);
    }

    public function test_qr_scan_returns_student_info(): void
    {
        Sanctum::actingAs($this->guard);

        $student = \App\Models\Student::factory()->create([
            'tenant_id' => $this->tenant->id,
            'hostel_id' => $this->hostel->id,
        ]);

        $qrData = json_encode([
            'student_uid' => $student->student_uid,
            'student_id' => $student->id,
        ]);

        $response = $this->postJson('/api/v1/gate/scan', [
            'qr_data' => $qrData,
            'action' => 'out',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'student' => [
                    'id',
                    'student_uid',
                    'name',
                    'hostel',
                ],
                'outpass',
                'action',
                'timestamp',
            ]);

        $data = $response->json();
        $this->assertEquals($student->id, $data['student']['id']);
        $this->assertEquals($student->student_uid, $data['student']['student_uid']);
        $this->assertEquals('out', $data['action']);
    }

    public function test_qr_scan_fails_with_invalid_data(): void
    {
        Sanctum::actingAs($this->guard);

        $response = $this->postJson('/api/v1/gate/scan', [
            'qr_data' => 'invalid_json',
            'action' => 'out',
        ]);

        $response->assertStatus(422)
            ->assertJson(['error' => 'Invalid QR code format']);
    }

    public function test_qr_scan_fails_with_missing_student_uid(): void
    {
        Sanctum::actingAs($this->guard);

        $qrData = json_encode([
            'student_id' => 123,
        ]);

        $response = $this->postJson('/api/v1/gate/scan', [
            'qr_data' => $qrData,
            'action' => 'out',
        ]);

        $response->assertStatus(422)
            ->assertJson(['error' => 'Invalid QR code format']);
    }

    public function test_qr_scan_returns_404_for_nonexistent_student(): void
    {
        Sanctum::actingAs($this->guard);

        $qrData = json_encode([
            'student_uid' => 'NONEXISTENT_UID',
        ]);

        $response = $this->postJson('/api/v1/gate/scan', [
            'qr_data' => $qrData,
            'action' => 'out',
        ]);

        $response->assertStatus(404)
            ->assertJson(['error' => 'Student not found']);
    }
}
