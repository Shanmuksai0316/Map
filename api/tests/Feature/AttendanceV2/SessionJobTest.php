<?php

namespace Tests\Feature\AttendanceV2;

use App\Domain\Attendance\Models\AttendanceSessionV2;
use App\Jobs\Attendance\OpenSessionsJob;
use App\Jobs\Attendance\ActivateSessionsJob;
use App\Jobs\Attendance\CloseSessionsJob;
use App\Models\Hostel;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SessionJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_sessions_job_creates_sessions_per_hostel_day(): void
    {
        $tenant = Tenant::factory()->create();
        $hostel1 = Hostel::factory()->create(['tenant_id' => $tenant->id]);
        $hostel2 = Hostel::factory()->create(['tenant_id' => $tenant->id]);
        
        $sessionDate = now('Asia/Kolkata')->toDateString();
        
        // Dispatch the job
        OpenSessionsJob::dispatch($sessionDate);
        
        // Assert sessions were created
        $this->assertDatabaseHas('attendance_sessions', [
            'hostel_id' => $hostel1->id,
            'session_date' => $sessionDate,
            'status' => 'scheduled',
            'tenant_id' => $tenant->id,
        ]);
        
        $this->assertDatabaseHas('attendance_sessions', [
            'hostel_id' => $hostel2->id,
            'session_date' => $sessionDate,
            'status' => 'scheduled',
            'tenant_id' => $tenant->id,
        ]);
        
        // Assert metadata was set
        $session = AttendanceSessionV2::where('hostel_id', $hostel1->id)
            ->where('session_date', $sessionDate)
            ->first();
            
        $this->assertNotNull($session->metadata['window']);
        $this->assertArrayHasKey('start', $session->metadata['window']);
        $this->assertArrayHasKey('end', $session->metadata['window']);
    }

    public function test_open_sessions_job_is_idempotent(): void
    {
        // Clear all existing data to ensure clean test
        AttendanceSessionV2::truncate();
        Hostel::truncate();
        
        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create(['tenant_id' => $tenant->id]);
        
        $sessionDate = now('Asia/Kolkata')->toDateString();
        
        // Create session manually first
        AttendanceSessionV2::create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'session_date' => $sessionDate,
            'status' => 'scheduled',
            'scheduled_at' => now(),
            'metadata' => ['test' => 'data'],
        ]);
        
        // Assert one session exists before job
        $this->assertDatabaseCount('attendance_sessions', 1);
        
        // Dispatch the job
        OpenSessionsJob::dispatch($sessionDate);
        
        // Assert still only one session exists
        $this->assertDatabaseCount('attendance_sessions', 1);
        
        // Assert metadata was updated
        $session = AttendanceSessionV2::first();
        $this->assertArrayHasKey('window', $session->metadata);
    }

    public function test_activate_sessions_job_activates_sessions_when_window_starts(): void
    {
        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create(['tenant_id' => $tenant->id]);
        
        $session = AttendanceSessionV2::create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'session_date' => now('Asia/Kolkata')->toDateString(),
            'status' => 'scheduled',
            'scheduled_at' => now(),
            'metadata' => [
                'window' => [
                    'start' => now('Asia/Kolkata')->subMinutes(10)->toISOString(),
                    'end' => now('Asia/Kolkata')->addHours(2)->toISOString(),
                ]
            ],
        ]);
        
        // Dispatch the job
        ActivateSessionsJob::dispatch();
        
        // Assert session was activated
        $session->refresh();
        $this->assertEquals('active', $session->status);
    }

    public function test_activate_sessions_job_does_not_activate_future_sessions(): void
    {
        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create(['tenant_id' => $tenant->id]);
        
        $session = AttendanceSessionV2::create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'session_date' => now('Asia/Kolkata')->toDateString(),
            'status' => 'scheduled',
            'scheduled_at' => now(),
            'metadata' => [
                'window' => [
                    'start' => now('Asia/Kolkata')->addMinutes(10)->toISOString(),
                    'end' => now('Asia/Kolkata')->addHours(2)->toISOString(),
                ]
            ],
        ]);
        
        // Dispatch the job
        ActivateSessionsJob::dispatch();
        
        // Assert session was not activated
        $session->refresh();
        $this->assertEquals('scheduled', $session->status);
    }

    public function test_close_sessions_job_closes_sessions_when_window_ends(): void
    {
        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create(['tenant_id' => $tenant->id]);
        
        $session = AttendanceSessionV2::create([
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'session_date' => now('Asia/Kolkata')->toDateString(),
            'status' => 'active',
            'scheduled_at' => now(),
            'metadata' => [
                'window' => [
                    'start' => now('Asia/Kolkata')->subHours(2)->toISOString(),
                    'end' => now('Asia/Kolkata')->subMinutes(10)->toISOString(),
                ]
            ],
        ]);
        
        // Dispatch the job
        $auditLogger = app(\App\Services\AuditLogger::class);
        CloseSessionsJob::dispatch($auditLogger);
        
        // Assert session was closed
        $session->refresh();
        $this->assertEquals('closed', $session->status);
    }
}
