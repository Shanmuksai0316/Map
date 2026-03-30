<?php

namespace Tests\Feature\Attendance;

use App\Domain\Attendance\Models\AttendanceSession;
use App\Jobs\AttendanceEnsureTodayJob;
use App\Models\Hostel;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttendanceSchedulingTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_creates_session_with_correct_timing(): void
    {
        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create([
            'tenant_id' => $tenant->id,
            'curfew_time' => '22:30:00',
        ]);

        // Time travel to 21:40 IST (within session window)
        Carbon::setTestNow(Carbon::create(2025, 9, 29, 21, 40, 0, 'Asia/Kolkata'));

        (new AttendanceEnsureTodayJob())->handle();

        $session = AttendanceSession::query()
            ->where('tenant_id', $tenant->id)
            ->where('hostel_id', $hostel->id)
            ->where('kind', 'night_check')
            ->first();

        $this->assertNotNull($session);
        $this->assertEquals('Open', $session->status);
        
        // Debug: check what's actually stored
        $openAt = Carbon::parse($session->metadata['open_at']);
        $closeAt = Carbon::parse($session->metadata['close_at']);
        
        $this->assertEquals('21:30', $openAt->setTimezone('Asia/Kolkata')->format('H:i'));
        $this->assertEquals('00:30', $closeAt->setTimezone('Asia/Kolkata')->format('H:i'));
        // Close time should be on the next day (00:30 next day)
        $this->assertTrue($closeAt->setTimezone('Asia/Kolkata')->isAfter($openAt->setTimezone('Asia/Kolkata')));
    }

    public function test_job_sets_scheduled_status_before_window(): void
    {
        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create([
            'tenant_id' => $tenant->id,
            'curfew_time' => '22:30:00',
        ]);

        // Time travel to 18:00 IST (before session window)
        Carbon::setTestNow(Carbon::create(2025, 9, 29, 18, 0, 0, 'Asia/Kolkata'));

        (new AttendanceEnsureTodayJob())->handle();

        $session = AttendanceSession::query()
            ->where('tenant_id', $tenant->id)
            ->where('hostel_id', $hostel->id)
            ->where('kind', 'night_check')
            ->first();

        $this->assertNotNull($session);
        $this->assertEquals('Scheduled', $session->status);
    }

    public function test_job_is_idempotent(): void
    {
        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create([
            'tenant_id' => $tenant->id,
            'curfew_time' => '22:30:00',
        ]);

        Carbon::setTestNow(Carbon::create(2025, 9, 29, 21, 40, 0, 'Asia/Kolkata'));

        // Run job twice
        (new AttendanceEnsureTodayJob())->handle();
        (new AttendanceEnsureTodayJob())->handle();

        $sessionCount = AttendanceSession::query()
            ->where('tenant_id', $tenant->id)
            ->where('hostel_id', $hostel->id)
            ->where('kind', 'night_check')
            ->count();

        $this->assertEquals(1, $sessionCount);
    }
}
