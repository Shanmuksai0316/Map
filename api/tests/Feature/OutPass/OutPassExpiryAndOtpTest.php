<?php

namespace Tests\Feature\OutPass;

use App\Enums\OutPassStatus;
use App\Models\Domain\OutPass\OutPass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StepUpOtpService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutPassExpiryAndOtpTest extends TestCase
{
    use RefreshDatabase;

    private User $student;
    private User $rector;
    private Tenant $tenant;
    private OutPass $outPass;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create();
        $this->student = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->rector = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'kind' => 'Rector'
        ]);
        
        // Create student record
        Student::factory()->create(['user_id' => $this->student->id, 'tenant_id' => $this->tenant->id]);
        
        // Create out-pass
        $this->outPass = OutPass::factory()->create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student->student->id,
            'status' => OutPassStatus::PENDING,
            'requested_at' => now()->subHours(25), // 25 hours ago
        ]);
    }

    public function test_outpass_automatically_expires_after_24_hours()
    {
        $response = $this->actingAs($this->rector)
            ->putJson("/api/v1/outpasses/{$this->outPass->id}", [
                'status' => 'approved',
                'note' => 'Approved by rector',
            ]);

        $response->assertStatus(200);

        $this->outPass->refresh();
        $this->assertEquals(OutPassStatus::EXPIRED, $this->outPass->status);
        $this->assertEquals('Automatically expired after 24 hours', $this->outPass->note);
        $this->assertNull($this->outPass->decision_by);
    }

    public function test_recent_outpass_does_not_expire()
    {
        // Create a recent out-pass (1 hour ago)
        $recentOutPass = OutPass::factory()->create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student->student->id,
            'status' => OutPassStatus::PENDING,
            'requested_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($this->rector)
            ->putJson("/api/v1/outpasses/{$recentOutPass->id}", [
                'status' => 'approved',
                'note' => 'Approved by rector',
            ]);

        $response->assertStatus(200);

        $recentOutPass->refresh();
        $this->assertEquals(OutPassStatus::APPROVED, $recentOutPass->status);
        $this->assertEquals('Approved by rector', $recentOutPass->note);
    }

    public function test_rector_approval_requires_step_up_otp()
    {
        // Create a recent out-pass
        $recentOutPass = OutPass::factory()->create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student->student->id,
            'status' => OutPassStatus::PENDING,
            'requested_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($this->rector)
            ->putJson("/api/v1/outpasses/{$recentOutPass->id}", [
                'status' => 'approved',
                'note' => 'Approved by rector',
            ]);

        $response->assertStatus(428)
            ->assertJson([
                'type' => 'https://map-hms.dev/errors/step_up_required',
                'title' => 'Step-Up Authentication Required',
                'step_up_required' => true,
                'action' => StepUpOtpService::PURPOSE_RECTOR_APPROVAL,
            ]);
    }

    public function test_rector_approval_with_valid_otp_succeeds()
    {
        // Create a recent out-pass
        $recentOutPass = OutPass::factory()->create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student->student->id,
            'status' => OutPassStatus::PENDING,
            'requested_at' => now()->subHour(),
        ]);

        // Mock OTP service to return true for recent verification
        $this->mock(StepUpOtpService::class, function ($mock) {
            $mock->shouldReceive('isRecentlyVerified')
                ->andReturn(true);
        });

        $response = $this->actingAs($this->rector)
            ->putJson("/api/v1/outpasses/{$recentOutPass->id}", [
                'status' => 'approved',
                'note' => 'Approved by rector',
            ]);

        $response->assertStatus(200);

        $recentOutPass->refresh();
        $this->assertEquals(OutPassStatus::APPROVED, $recentOutPass->status);
    }

    public function test_non_rector_can_approve_without_otp()
    {
        $warden = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'kind' => 'Warden'
        ]);

        // Create a recent out-pass
        $recentOutPass = OutPass::factory()->create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student->student->id,
            'status' => OutPassStatus::PENDING,
            'requested_at' => now()->subHour(),
        ]);

        $response = $this->actingAs($warden)
            ->putJson("/api/v1/outpasses/{$recentOutPass->id}", [
                'status' => 'approved',
                'note' => 'Approved by warden',
            ]);

        $response->assertStatus(200);

        $recentOutPass->refresh();
        $this->assertEquals(OutPassStatus::APPROVED, $recentOutPass->status);
    }

    public function test_expired_outpass_creates_audit_history()
    {
        $response = $this->actingAs($this->rector)
            ->putJson("/api/v1/outpasses/{$this->outPass->id}", [
                'status' => 'approved',
                'note' => 'This should not be used',
            ]);

        $response->assertStatus(200);

        // Check that history was recorded
        $this->assertDatabaseHas('out_pass_histories', [
            'out_pass_id' => $this->outPass->id,
            'from_status' => OutPassStatus::PENDING->value,
            'to_status' => OutPassStatus::EXPIRED->value,
            'note' => 'Automatically expired',
        ]);
    }
}



