<?php

namespace Tests\Feature\Attendance;

use App\Domain\Attendance\Models\AttendanceSessionV2;
use App\Jobs\Attendance\OpenSessionsForTenantJob;
use App\Jobs\Attendance\OpenSessionsJob;
use App\Models\Hostel;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AttendanceJobsTenantScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_sessions_job_dispatches_per_tenant()
    {
        Queue::fake();

        // Create multiple tenants with hostels
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();
        
        Hostel::factory()->create(['tenant_id' => $tenant1->id]);
        Hostel::factory()->create(['tenant_id' => $tenant2->id]);

        // Dispatch the main job
        OpenSessionsJob::dispatch();

        // Should dispatch one job per tenant
        Queue::assertPushed(OpenSessionsForTenantJob::class, 2);
        
        Queue::assertPushed(OpenSessionsForTenantJob::class, function ($job) use ($tenant1) {
            return $job->tenantId === $tenant1->id;
        });
        
        Queue::assertPushed(OpenSessionsForTenantJob::class, function ($job) use ($tenant2) {
            return $job->tenantId === $tenant2->id;
        });
    }

    public function test_tenant_specific_job_creates_sessions_for_tenant_only()
    {
        // Create two tenants with hostels
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();
        
        $hostel1 = Hostel::factory()->create(['tenant_id' => $tenant1->id]);
        $hostel2 = Hostel::factory()->create(['tenant_id' => $tenant2->id]);

        // Run job for tenant1 only
        OpenSessionsForTenantJob::dispatch($tenant1->id);

        // Should create session for tenant1's hostel only
        $this->assertDatabaseHas('attendance_sessions_v2', [
            'tenant_id' => $tenant1->id,
            'hostel_id' => $hostel1->id,
        ]);

        $this->assertDatabaseMissing('attendance_sessions_v2', [
            'tenant_id' => $tenant2->id,
            'hostel_id' => $hostel2->id,
        ]);
    }

    public function test_tenant_job_handles_multiple_hostels()
    {
        $tenant = Tenant::factory()->create();
        $hostel1 = Hostel::factory()->create(['tenant_id' => $tenant->id]);
        $hostel2 = Hostel::factory()->create(['tenant_id' => $tenant->id]);

        OpenSessionsForTenantJob::dispatch($tenant->id);

        // Should create sessions for both hostels
        $this->assertDatabaseHas('attendance_sessions_v2', [
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel1->id,
        ]);

        $this->assertDatabaseHas('attendance_sessions_v2', [
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel2->id,
        ]);
    }

    public function test_tenant_job_is_idempotent()
    {
        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create(['tenant_id' => $tenant->id]);

        // Run job twice
        OpenSessionsForTenantJob::dispatch($tenant->id);
        OpenSessionsForTenantJob::dispatch($tenant->id);

        // Should only have one session per hostel
        $this->assertEquals(1, AttendanceSessionV2::where('hostel_id', $hostel->id)->count());
    }

    public function test_tenant_job_with_custom_date()
    {
        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create(['tenant_id' => $tenant->id]);
        $customDate = '2025-01-20';

        OpenSessionsForTenantJob::dispatch($tenant->id, $customDate);

        $this->assertDatabaseHas('attendance_sessions_v2', [
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'session_date' => $customDate,
        ]);
    }
}



