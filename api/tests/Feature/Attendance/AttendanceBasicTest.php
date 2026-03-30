<?php

namespace Tests\Feature\Attendance;

use App\Domain\Attendance\Models\AttendanceSession;
use App\Jobs\AttendanceEnsureTodayJob;
use App\Models\Hostel;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AttendanceBasicTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_job_creates_session(): void
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
        $this->assertEquals('Night Check - ' . $hostel->name, $session->name);
    }

    public function test_attendance_session_has_correct_metadata(): void
    {
        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create([
            'tenant_id' => $tenant->id,
            'curfew_time' => '22:30:00',
        ]);

        Carbon::setTestNow(Carbon::create(2025, 9, 29, 21, 40, 0, 'Asia/Kolkata'));

        (new AttendanceEnsureTodayJob())->handle();

        $session = AttendanceSession::query()
            ->where('tenant_id', $tenant->id)
            ->where('hostel_id', $hostel->id)
            ->where('kind', 'night_check')
            ->first();

        $this->assertNotNull($session->metadata);
        $this->assertArrayHasKey('open_at', $session->metadata);
        $this->assertArrayHasKey('close_at', $session->metadata);
        $this->assertArrayHasKey('session_date', $session->metadata);
    }

    public function test_attendance_session_is_open_when_within_window(): void
    {
        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create([
            'tenant_id' => $tenant->id,
            'curfew_time' => '22:30:00',
        ]);

        Carbon::setTestNow(Carbon::create(2025, 9, 29, 21, 40, 0, 'Asia/Kolkata'));

        (new AttendanceEnsureTodayJob())->handle();

        $session = AttendanceSession::query()
            ->where('tenant_id', $tenant->id)
            ->where('hostel_id', $hostel->id)
            ->where('kind', 'night_check')
            ->first();

        $this->assertTrue($session->isOpen());
    }

    public function test_attendance_session_is_scheduled_when_before_window(): void
    {
        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create([
            'tenant_id' => $tenant->id,
            'curfew_time' => '22:30:00',
        ]);

        Carbon::setTestNow(Carbon::create(2025, 9, 29, 18, 0, 0, 'Asia/Kolkata'));

        (new AttendanceEnsureTodayJob())->handle();

        $session = AttendanceSession::query()
            ->where('tenant_id', $tenant->id)
            ->where('hostel_id', $hostel->id)
            ->where('kind', 'night_check')
            ->first();

        $this->assertEquals('Scheduled', $session->status);
        $this->assertFalse($session->isOpen());
    }
}
