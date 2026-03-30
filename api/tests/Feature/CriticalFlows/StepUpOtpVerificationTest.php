<?php

namespace Tests\Feature\CriticalFlows;

use App\Enums\OutPassStatus;
use App\Models\Campus;
use App\Models\Domain\OutPass\OutPass;
use App\Models\Hostel;
use App\Models\StepUpOtpSession;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StepUpOtpService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Step-Up OTP Verification Tests
 * 
 * Tests for the security-critical step-up OTP flow used for:
 * - Rector approvals
 * - Sensitive actions
 * - Tenant activation
 * - Impersonation
 */
class StepUpOtpVerificationTest extends TestCase
{
    private User $rector;
    private Tenant $tenant;
    private Hostel $hostel;
    private Campus $campus;

    protected function setUp(): void
    {
        parent::setUp();
        
        Cache::flush();

        // Create roles
        Role::findOrCreate('Rector', 'sanctum');
        Role::findOrCreate('Campus Manager', 'sanctum');

        // Create tenant context in PROVISIONING status first
        $this->tenant = Tenant::factory()->create([
            'status' => \App\Enums\TenantStatus::PROVISIONING,
        ]);
        $this->campus = Campus::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->hostel = Hostel::factory()->create([
            'tenant_id' => $this->tenant->id,
            'campus_id' => $this->campus->id,
        ]);
        
        // Now activate
        $this->tenant->update(['status' => \App\Enums\TenantStatus::ACTIVE]);

        // Create rector user
        $this->rector = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'kind' => 'Rector',
            'phone' => '+919876543210',
        ]);
        $this->rector->assignRole('Rector');
    }

    /**
     * Test: Step-up OTP initiation returns session details
     */
    public function test_step_up_otp_initiation_returns_session(): void
    {
        /** @var StepUpOtpService $service */
        $service = app(StepUpOtpService::class);

        $result = $service->startOtp(
            $this->rector,
            StepUpOtpService::PURPOSE_RECTOR_APPROVAL,
            'sms'
        );

        $this->assertArrayHasKey('session_id', $result);
        $this->assertArrayHasKey('expires_in_minutes', $result);
        $this->assertArrayHasKey('channel', $result);
        $this->assertEquals('sms', $result['channel']);
        $this->assertEquals(10, $result['expires_in_minutes']);

        // Verify session was created in database
        $this->assertDatabaseHas('step_up_otp_sessions', [
            'user_id' => $this->rector->id,
            'action_type' => StepUpOtpService::PURPOSE_RECTOR_APPROVAL,
            'verified' => false,
        ]);
    }

    /**
     * Test: Step-up OTP verification with correct code
     */
    public function test_step_up_otp_verification_with_correct_code(): void
    {
        /** @var StepUpOtpService $service */
        $service = app(StepUpOtpService::class);

        // Generate a known OTP for testing
        $testOtp = '123456';
        $hashedOtp = StepUpOtpSession::hashOtpCode($testOtp);

        // Create session directly with known OTP
        $session = StepUpOtpSession::createSession(
            $this->rector->id,
            $this->rector->phone,
            $hashedOtp,
            StepUpOtpService::PURPOSE_RECTOR_APPROVAL,
            ['channel' => 'sms']
        );

        // Verify with correct code
        $result = $service->verifyOtp(
            $this->rector,
            StepUpOtpService::PURPOSE_RECTOR_APPROVAL,
            $testOtp
        );

        $this->assertTrue($result);

        // Verify session is marked as verified
        $session->refresh();
        $this->assertTrue($session->verified);
        $this->assertNotNull($session->verified_at);
    }

    /**
     * Test: Step-up OTP verification fails with incorrect code
     */
    public function test_step_up_otp_verification_fails_with_wrong_code(): void
    {
        /** @var StepUpOtpService $service */
        $service = app(StepUpOtpService::class);

        // Create session with known OTP
        $testOtp = '123456';
        $hashedOtp = StepUpOtpSession::hashOtpCode($testOtp);

        StepUpOtpSession::createSession(
            $this->rector->id,
            $this->rector->phone,
            $hashedOtp,
            StepUpOtpService::PURPOSE_RECTOR_APPROVAL,
            ['channel' => 'sms']
        );

        // Verify with wrong code
        $result = $service->verifyOtp(
            $this->rector,
            StepUpOtpService::PURPOSE_RECTOR_APPROVAL,
            '999999' // Wrong code
        );

        $this->assertFalse($result);
    }

    /**
     * Test: Step-up OTP expires after timeout
     */
    public function test_step_up_otp_expires_after_timeout(): void
    {
        /** @var StepUpOtpService $service */
        $service = app(StepUpOtpService::class);

        $testOtp = '123456';
        $hashedOtp = StepUpOtpSession::hashOtpCode($testOtp);

        // Create expired session
        $session = StepUpOtpSession::createSession(
            $this->rector->id,
            $this->rector->phone,
            $hashedOtp,
            StepUpOtpService::PURPOSE_RECTOR_APPROVAL,
            ['channel' => 'sms']
        );

        // Manually expire the session
        $session->update(['expires_at' => now()->subMinutes(5)]);

        // Verification should fail
        $result = $service->verifyOtp(
            $this->rector,
            StepUpOtpService::PURPOSE_RECTOR_APPROVAL,
            $testOtp
        );

        $this->assertFalse($result);
    }

    /**
     * Test: Recently verified user doesn't need step-up again
     */
    public function test_recently_verified_user_doesnt_need_step_up(): void
    {
        /** @var StepUpOtpService $service */
        $service = app(StepUpOtpService::class);

        // Create and verify a session
        $testOtp = '123456';
        $hashedOtp = StepUpOtpSession::hashOtpCode($testOtp);

        $session = StepUpOtpSession::createSession(
            $this->rector->id,
            $this->rector->phone,
            $hashedOtp,
            StepUpOtpService::PURPOSE_RECTOR_APPROVAL,
            ['channel' => 'sms']
        );

        $session->markAsVerified();

        // Check if step-up is required
        $isRequired = $service->isStepUpRequired(
            $this->rector,
            StepUpOtpService::PURPOSE_RECTOR_APPROVAL
        );

        $this->assertFalse($isRequired);
        $this->assertTrue($service->isRecentlyVerified(
            $this->rector,
            StepUpOtpService::PURPOSE_RECTOR_APPROVAL
        ));
    }

    /**
     * Test: Rate limiting on OTP requests
     */
    public function test_rate_limiting_on_otp_requests(): void
    {
        /** @var StepUpOtpService $service */
        $service = app(StepUpOtpService::class);

        // Create 5 sessions (the limit)
        for ($i = 0; $i < 5; $i++) {
            StepUpOtpSession::create([
                'session_id' => 'test-session-' . $i,
                'user_id' => $this->rector->id,
                'phone_number' => $this->rector->phone,
                'otp_code' => StepUpOtpSession::hashOtpCode('123456'),
                'action_type' => StepUpOtpService::PURPOSE_RECTOR_APPROVAL,
                'expires_at' => now()->addMinutes(10),
                'verified' => false,
                'metadata' => ['channel' => 'sms'],
            ]);
        }

        // 6th request should be rate limited
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Too many OTP requests');

        $service->startOtp(
            $this->rector,
            StepUpOtpService::PURPOSE_RECTOR_APPROVAL,
            'sms'
        );
    }

    /**
     * Test: Rector approval requires step-up OTP via service check
     */
    public function test_rector_outpass_approval_requires_step_up_otp_api(): void
    {
        // Clear any existing OTP sessions
        StepUpOtpSession::where('user_id', $this->rector->id)->delete();

        // Verify step-up is required
        $service = app(StepUpOtpService::class);
        $isRequired = $service->isStepUpRequired(
            $this->rector,
            StepUpOtpService::PURPOSE_RECTOR_APPROVAL
        );

        $this->assertTrue($isRequired, 'Rector should require step-up OTP');
    }

    /**
     * Test: Rector can approve after step-up OTP verification
     */
    public function test_rector_can_approve_after_step_up_otp_verification(): void
    {
        // Create and verify a step-up OTP session
        $testOtp = '123456';
        $hashedOtp = StepUpOtpSession::hashOtpCode($testOtp);

        $session = StepUpOtpSession::createSession(
            $this->rector->id,
            $this->rector->phone,
            $hashedOtp,
            StepUpOtpService::PURPOSE_RECTOR_APPROVAL,
            ['channel' => 'sms']
        );
        $session->markAsVerified();

        // Verify step-up is no longer required
        $service = app(StepUpOtpService::class);
        $isRequired = $service->isStepUpRequired(
            $this->rector,
            StepUpOtpService::PURPOSE_RECTOR_APPROVAL
        );

        $this->assertFalse($isRequired, 'Rector should not require step-up after verification');
    }

    /**
     * Test: Campus Manager doesn't need step-up for approvals
     */
    public function test_campus_manager_doesnt_need_step_up(): void
    {
        $campusManager = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'kind' => 'CampusManager',
            'phone' => '+919999999999',
        ]);
        $campusManager->assignRole('Campus Manager');

        Sanctum::actingAs($campusManager);

        /** @var StepUpOtpService $service */
        $service = app(StepUpOtpService::class);

        $isRequired = $service->isStepUpRequired(
            $campusManager,
            StepUpOtpService::PURPOSE_RECTOR_APPROVAL
        );

        $this->assertFalse($isRequired);
    }

    /**
     * Test: Cleanup of expired sessions
     */
    public function test_cleanup_expired_sessions(): void
    {
        /** @var StepUpOtpService $service */
        $service = app(StepUpOtpService::class);

        // Create some expired sessions
        for ($i = 0; $i < 3; $i++) {
            StepUpOtpSession::create([
                'session_id' => 'expired-session-' . $i,
                'user_id' => $this->rector->id,
                'phone_number' => $this->rector->phone,
                'otp_code' => StepUpOtpSession::hashOtpCode('123456'),
                'action_type' => StepUpOtpService::PURPOSE_RECTOR_APPROVAL,
                'expires_at' => now()->subMinutes(30),
                'verified' => false,
                'metadata' => ['channel' => 'sms'],
            ]);
        }

        // Create one non-expired session
        StepUpOtpSession::create([
            'session_id' => 'valid-session',
            'user_id' => $this->rector->id,
            'phone_number' => $this->rector->phone,
            'otp_code' => StepUpOtpSession::hashOtpCode('123456'),
            'action_type' => StepUpOtpService::PURPOSE_RECTOR_APPROVAL,
            'expires_at' => now()->addMinutes(5),
            'verified' => false,
            'metadata' => ['channel' => 'sms'],
        ]);

        $this->assertEquals(4, StepUpOtpSession::where('user_id', $this->rector->id)->count());

        $deleted = $service->cleanupExpiredSessions();

        $this->assertEquals(3, $deleted);
        $this->assertEquals(1, StepUpOtpSession::where('user_id', $this->rector->id)->count());
    }

    /**
     * Test: OTP statistics tracking
     */
    public function test_otp_statistics_tracking(): void
    {
        /** @var StepUpOtpService $service */
        $service = app(StepUpOtpService::class);

        // Create some sessions with different states
        // 2 verified
        for ($i = 0; $i < 2; $i++) {
            $session = StepUpOtpSession::create([
                'session_id' => 'verified-' . $i,
                'user_id' => $this->rector->id,
                'phone_number' => $this->rector->phone,
                'otp_code' => StepUpOtpSession::hashOtpCode('123456'),
                'action_type' => StepUpOtpService::PURPOSE_RECTOR_APPROVAL,
                'expires_at' => now()->addMinutes(10),
                'verified' => true,
                'verified_at' => now(),
                'metadata' => ['channel' => 'sms'],
            ]);
        }

        // 1 expired
        StepUpOtpSession::create([
            'session_id' => 'expired-stat',
            'user_id' => $this->rector->id,
            'phone_number' => $this->rector->phone,
            'otp_code' => StepUpOtpSession::hashOtpCode('123456'),
            'action_type' => StepUpOtpService::PURPOSE_RECTOR_APPROVAL,
            'expires_at' => now()->subMinutes(5),
            'verified' => false,
            'metadata' => ['channel' => 'sms'],
        ]);

        $stats = $service->getStats($this->rector, 30);

        $this->assertEquals(3, $stats['total_requests']);
        $this->assertEquals(2, $stats['successful_verifications']);
        $this->assertEquals(1, $stats['expired_sessions']);
    }
}

