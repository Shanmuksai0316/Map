<?php

namespace Tests\Feature\Rector;

use App\Enums\OutPassStatus;
use App\Models\Campus;
use App\Models\Domain\OutPass\OutPass;
use App\Models\Hostel;
use App\Models\Incident;
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

class RectorDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Campus $campus;
    private Hostel $hostel1;
    private Hostel $hostel2;
    private User $rector;

    protected function setUp(): void
    {
        parent::setUp();

        // Use pre-seeded tenant from TestBootstrapSeeder
        $this->tenant = Tenant::find(1);
        $this->campus = Campus::find(1);
        
        // Create additional hostels for multi-campus testing
        $this->hostel1 = Hostel::find(1); // Pre-seeded hostel
        
        $this->hostel2 = Hostel::factory()->create([
            'tenant_id' => $this->tenant->id,
            'campus_id' => $this->campus->id,
        ]);

        // Create Rector role and user
        Role::findOrCreate('Rector');
        $this->rector = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'kind' => 'Rector',
        ]);
        $this->rector->assignRole('Rector');
    }

    public function test_dashboard_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/rector/dashboard');
        $response->assertUnauthorized();
    }

    public function test_dashboard_requires_rector_role(): void
    {
        // Create a warden (non-rector role)
        Role::findOrCreate('Warden');
        $warden = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'kind' => 'Warden',
        ]);
        $warden->assignRole('Warden');

        Sanctum::actingAs($warden);

        $response = $this->getJson('/api/v1/rector/dashboard');
        $response->assertForbidden();
    }

    public function test_dashboard_returns_campus_wide_metrics(): void
    {
        Sanctum::actingAs($this->rector);

        // Create beds across both hostels
        $this->createBedsInHostel($this->hostel1, 4, 2); // 4 total, 2 occupied
        $this->createBedsInHostel($this->hostel2, 6, 3); // 6 total, 3 occupied

        // Create pending out-passes
        $this->createOutPass($this->hostel1, OutPassStatus::PENDING, now()->subHours(2));
        $this->createOutPass($this->hostel2, OutPassStatus::PENDING, now()->subHours(5));

        // Create incidents
        $this->createIncident($this->hostel1, 'LateReturn', now());
        $this->createIncident($this->hostel2, 'Security', now());

        $response = $this->getJson('/api/v1/rector/dashboard');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'occupancy' => ['total_beds', 'occupied', 'available', 'percent'],
                    'pending_approvals' => ['count', 'median_age_hours'],
                    'incidents_today' => ['count', 'by_type'],
                    'late_returns_today',
                    'late_returns_7d',
                    'auto_expired_24h',
                    'hostels_summary',
                ],
            ]);

        // Verify campus-wide aggregation
        $data = $response->json('data');
        $this->assertEquals(10, $data['occupancy']['total_beds']); // 4 + 6
        $this->assertEquals(5, $data['occupancy']['occupied']); // 2 + 3
        $this->assertEquals(5, $data['occupancy']['available']); // 5 - 5
        $this->assertEquals(50.0, $data['occupancy']['percent']); // 50%
        
        $this->assertEquals(2, $data['pending_approvals']['count']);
        $this->assertGreaterThan(3, $data['pending_approvals']['median_age_hours']);
        
        $this->assertEquals(2, $data['incidents_today']['count']);
        $this->assertArrayHasKey('LateReturn', $data['incidents_today']['by_type']);
        $this->assertArrayHasKey('Security', $data['incidents_today']['by_type']);
    }

    public function test_dashboard_shows_only_current_tenant_data(): void
    {
        Sanctum::actingAs($this->rector);

        // Create data for current tenant only
        $this->createBedsInHostel($this->hostel1, 4, 2);
        $this->createOutPass($this->hostel1, OutPassStatus::PENDING);

        $response = $this->getJson('/api/v1/rector/dashboard');

        $data = $response->json('data');
        
        // Should see current tenant's data
        $this->assertEquals(4, $data['occupancy']['total_beds']);
        $this->assertEquals(1, $data['pending_approvals']['count']);
        
        // Verify tenant_id is enforced (all hostels should belong to same tenant)
        foreach ($data['hostels_summary'] as $hostel) {
            $actualHostel = Hostel::find($hostel['id']);
            $this->assertEquals($this->tenant->id, $actualHostel->tenant_id);
        }
    }

    public function test_approval_queue_returns_pending_outpasses(): void
    {
        Sanctum::actingAs($this->rector);

        $student = Student::factory()->create([
            'tenant_id' => $this->tenant->id,
            'hostel_id' => $this->hostel1->id,
        ]);

        // Create pending out-passes
        OutPass::factory()->create([
            'tenant_id' => $this->tenant->id,
            'hostel_id' => $this->hostel1->id,
            'student_id' => $student->id,
            'status' => OutPassStatus::PENDING,
            'requested_at' => now()->subHours(3),
        ]);

        // Create approved out-pass (should not appear)
        OutPass::factory()->create([
            'tenant_id' => $this->tenant->id,
            'hostel_id' => $this->hostel1->id,
            'student_id' => $student->id,
            'status' => OutPassStatus::APPROVED,
            'requested_at' => now()->subHour(),
        ]);

        $response = $this->getJson('/api/v1/rector/approvals');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'student_id',
                        'student_name',
                        'student_room',
                        'hostel_id',
                        'hostel_name',
                        'reason',
                        'overnight',
                        'requested_at',
                        'valid_until',
                        'hours_pending',
                    ],
                ],
                'meta',
            ]);

        // Should only return pending
        $this->assertCount(1, $response->json('data'));
        $this->assertGreaterThan(2, $response->json('data.0.hours_pending'));
    }

    public function test_approval_queue_filters_by_hostel(): void
    {
        Sanctum::actingAs($this->rector);

        $student1 = Student::factory()->create([
            'tenant_id' => $this->tenant->id,
            'hostel_id' => $this->hostel1->id,
        ]);

        $student2 = Student::factory()->create([
            'tenant_id' => $this->tenant->id,
            'hostel_id' => $this->hostel2->id,
        ]);

        OutPass::factory()->create([
            'tenant_id' => $this->tenant->id,
            'hostel_id' => $this->hostel1->id,
            'student_id' => $student1->id,
            'status' => OutPassStatus::PENDING,
        ]);

        OutPass::factory()->create([
            'tenant_id' => $this->tenant->id,
            'hostel_id' => $this->hostel2->id,
            'student_id' => $student2->id,
            'status' => OutPassStatus::PENDING,
        ]);

        // Filter by hostel1
        $response = $this->getJson("/api/v1/rector/approvals?hostel_id={$this->hostel1->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($this->hostel1->id, $response->json('data.0.hostel_id'));
    }

    public function test_approval_queue_filters_by_reason(): void
    {
        Sanctum::actingAs($this->rector);

        $student = Student::factory()->create([
            'tenant_id' => $this->tenant->id,
            'hostel_id' => $this->hostel1->id,
        ]);

        OutPass::factory()->create([
            'tenant_id' => $this->tenant->id,
            'hostel_id' => $this->hostel1->id,
            'student_id' => $student->id,
            'status' => OutPassStatus::PENDING,
            'reason' => 'Normal',
        ]);

        OutPass::factory()->create([
            'tenant_id' => $this->tenant->id,
            'hostel_id' => $this->hostel1->id,
            'student_id' => $student->id,
            'status' => OutPassStatus::PENDING,
            'reason' => 'Leave',
        ]);

        // Filter by reason=Normal
        $response = $this->getJson('/api/v1/rector/approvals?reason=Normal');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('Normal', $response->json('data.0.reason'));
    }

    public function test_approval_queue_filters_by_overnight(): void
    {
        Sanctum::actingAs($this->rector);

        $student = Student::factory()->create([
            'tenant_id' => $this->tenant->id,
            'hostel_id' => $this->hostel1->id,
        ]);

        OutPass::factory()->create([
            'tenant_id' => $this->tenant->id,
            'hostel_id' => $this->hostel1->id,
            'student_id' => $student->id,
            'status' => OutPassStatus::PENDING,
            'overnight' => true,
        ]);

        OutPass::factory()->create([
            'tenant_id' => $this->tenant->id,
            'hostel_id' => $this->hostel1->id,
            'student_id' => $student->id,
            'status' => OutPassStatus::PENDING,
            'overnight' => false,
        ]);

        // Filter overnight=true
        $response = $this->getJson('/api/v1/rector/approvals?overnight=1');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertTrue($response->json('data.0.overnight'));
    }

    public function test_incidents_endpoint_returns_incidents(): void
    {
        Sanctum::actingAs($this->rector);

        $student = Student::factory()->create([
            'tenant_id' => $this->tenant->id,
            'hostel_id' => $this->hostel1->id,
        ]);

        Incident::factory()->create([
            'tenant_id' => $this->tenant->id,
            'hostel_id' => $this->hostel1->id,
            'student_id' => $student->id,
            'type' => 'LateReturn',
            'status' => 'open',
            'opened_at' => now(),
        ]);

        Incident::factory()->create([
            'tenant_id' => $this->tenant->id,
            'hostel_id' => $this->hostel2->id,
            'type' => 'Security',
            'status' => 'resolved',
            'opened_at' => now()->subDay(),
            'closed_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/rector/incidents');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'type',
                        'status',
                        'hostel_id',
                        'hostel_name',
                        'student_id',
                        'student_name',
                        'description',
                        'opened_at',
                        'closed_at',
                    ],
                ],
            ]);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_incidents_filters_by_hostel(): void
    {
        Sanctum::actingAs($this->rector);

        Incident::factory()->create([
            'tenant_id' => $this->tenant->id,
            'hostel_id' => $this->hostel1->id,
            'type' => 'LateReturn',
            'status' => 'open',
        ]);

        Incident::factory()->create([
            'tenant_id' => $this->tenant->id,
            'hostel_id' => $this->hostel2->id,
            'type' => 'Security',
            'status' => 'open',
        ]);

        $response = $this->getJson("/api/v1/rector/incidents?hostel_id={$this->hostel1->id}");

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($this->hostel1->id, $response->json('data.0.hostel_id'));
    }

    public function test_incidents_filters_by_type(): void
    {
        Sanctum::actingAs($this->rector);

        Incident::factory()->create([
            'tenant_id' => $this->tenant->id,
            'hostel_id' => $this->hostel1->id,
            'type' => 'LateReturn',
            'status' => 'open',
        ]);

        Incident::factory()->create([
            'tenant_id' => $this->tenant->id,
            'hostel_id' => $this->hostel1->id,
            'type' => 'Security',
            'status' => 'open',
        ]);

        $response = $this->getJson('/api/v1/rector/incidents?type=LateReturn');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('LateReturn', $response->json('data.0.type'));
    }

    public function test_hostel_health_returns_per_hostel_metrics(): void
    {
        Sanctum::actingAs($this->rector);

        // Create incidents for hostel1
        Incident::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'hostel_id' => $this->hostel1->id,
            'status' => 'open',
        ]);

        $response = $this->getJson('/api/v1/rector/hostels/health');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'hostel_id',
                        'hostel_name',
                        'open_incidents',
                        'maintenance_pending',
                        'last_inspection',
                    ],
                ],
            ]);

        // Find hostel1 in results
        $hostel1Data = collect($response->json('data'))
            ->firstWhere('hostel_id', $this->hostel1->id);

        $this->assertNotNull($hostel1Data);
        $this->assertEquals(2, $hostel1Data['open_incidents']);
    }

    public function test_analytics_returns_turnaround_and_trends(): void
    {
        Sanctum::actingAs($this->rector);

        $student = Student::factory()->create([
            'tenant_id' => $this->tenant->id,
            'hostel_id' => $this->hostel1->id,
        ]);

        // Create approved out-pass with turnaround time
        OutPass::factory()->create([
            'tenant_id' => $this->tenant->id,
            'hostel_id' => $this->hostel1->id,
            'student_id' => $student->id,
            'status' => OutPassStatus::APPROVED,
            'requested_at' => now()->subHours(5),
            'decided_at' => now()->subHours(3), // 2 hour turnaround
        ]);

        $response = $this->getJson('/api/v1/rector/analytics');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'approval_turnaround' => ['avg_hours', 'median_hours', 'total_decisions'],
                    'incident_categories',
                    'occupancy_trend',
                ],
            ]);

        $data = $response->json('data');
        $this->assertEquals(1, $data['approval_turnaround']['total_decisions']);
        $this->assertGreaterThanOrEqual(0, $data['approval_turnaround']['avg_hours']);
    }

    // Helper methods

    private function createBedsInHostel(Hostel $hostel, int $total, int $occupied): void
    {
        $room = Room::factory()->create([
            'tenant_id' => $hostel->tenant_id,
            'hostel_id' => $hostel->id,
        ]);

        for ($i = 0; $i < $total; $i++) {
            $bed = RoomBed::create([
                'tenant_id' => $hostel->tenant_id,
                'hostel_id' => $hostel->id,
                'room_id' => $room->id,
                'code' => chr(65 + $i), // A, B, C, etc.
                'status' => 'available',
            ]);

            if ($i < $occupied) {
                $student = Student::factory()->create([
                    'tenant_id' => $hostel->tenant_id,
                    'hostel_id' => $hostel->id,
                ]);

                RoomAllocation::create([
                    'tenant_id' => $hostel->tenant_id,
                    'hostel_id' => $hostel->id,
                    'student_id' => $student->id,
                    'room_bed_id' => $bed->id,
                    'is_active' => true,
                    'effective_from' => now(),
                ]);
            }
        }
    }

    private function createOutPass(Hostel $hostel, OutPassStatus $status, ?Carbon $requestedAt = null): OutPass
    {
        $student = Student::factory()->create([
            'tenant_id' => $hostel->tenant_id,
            'hostel_id' => $hostel->id,
        ]);

        return OutPass::factory()->create([
            'tenant_id' => $hostel->tenant_id,
            'hostel_id' => $hostel->id,
            'student_id' => $student->id,
            'status' => $status,
            'requested_at' => $requestedAt ?? now(),
        ]);
    }

    private function createIncident(Hostel $hostel, string $type, Carbon $openedAt): Incident
    {
        $student = Student::factory()->create([
            'tenant_id' => $hostel->tenant_id,
            'hostel_id' => $hostel->id,
        ]);

        return Incident::factory()->create([
            'tenant_id' => $hostel->tenant_id,
            'hostel_id' => $hostel->id,
            'student_id' => $student->id,
            'type' => $type,
            'status' => 'open',
            'opened_at' => $openedAt,
        ]);
    }
}

