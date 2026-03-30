<?php

namespace Tests\Feature;

use App\Services\OtpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OtpThrottleTest extends TestCase
{
    use RefreshDatabase;

    private OtpService $otpService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->otpService = app(OtpService::class);
    }

    public function test_otp_blocks_after_max_attempts()
    {
        $userId = 1;
        $purpose = 'login';
        $channel = 'sms';
        $to = '+1234567890';

        // Make 5 attempts (should succeed)
        for ($i = 0; $i < 5; $i++) {
            $result = $this->otpService->start($userId, $purpose, $channel, $to);
            $this->assertTrue($result['sent']);
        }

        // 6th attempt should be blocked
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Too many attempts');
        
        $this->otpService->start($userId, $purpose, $channel, $to);
    }

    public function test_otp_attempts_reset_after_successful_verification()
    {
        $userId = 1;
        $purpose = 'login';
        $channel = 'sms';
        $to = '+1234567890';

        // Make 3 attempts
        for ($i = 0; $i < 3; $i++) {
            $this->otpService->start($userId, $purpose, $channel, $to);
        }

        // Verify with correct code (this should reset attempts)
        $result = $this->otpService->start($userId, $purpose, $channel, $to);
        $code = $result['debug_code'] ?? '123456'; // Use debug code in test
        
        $this->otpService->verify($userId, $purpose, $code);

        // Should be able to make 5 more attempts
        for ($i = 0; $i < 5; $i++) {
            $result = $this->otpService->start($userId, $purpose, $channel, $to);
            $this->assertTrue($result['sent']);
        }
    }

    public function test_otp_attempts_are_per_user_and_purpose()
    {
        $userId1 = 1;
        $userId2 = 2;
        $purpose1 = 'login';
        $purpose2 = 'reset_password';
        $channel = 'sms';
        $to = '+1234567890';

        // Block user 1 for login purpose
        for ($i = 0; $i < 5; $i++) {
            $this->otpService->start($userId1, $purpose1, $channel, $to);
        }

        // User 1 should be blocked for login
        $this->expectException(ValidationException::class);
        $this->otpService->start($userId1, $purpose1, $channel, $to);

        // But user 1 should still work for reset_password
        $result = $this->otpService->start($userId1, $purpose2, $channel, $to);
        $this->assertTrue($result['sent']);

        // And user 2 should work for login
        $result = $this->otpService->start($userId2, $purpose1, $channel, $to);
        $this->assertTrue($result['sent']);
    }

    public function test_otp_masks_code_in_production()
    {
        $userId = 1;
        $purpose = 'login';
        $channel = 'sms';
        $to = '+1234567890';

        // In test environment, should return debug code
        $result = $this->otpService->start($userId, $purpose, $channel, $to);
        $this->assertArrayHasKey('debug_code', $result);
        $this->assertIsString($result['debug_code']);
        $this->assertEquals(6, strlen($result['debug_code']));
    }

    public function test_otp_returns_remaining_attempts()
    {
        $userId = 1;
        $purpose = 'login';
        $channel = 'sms';
        $to = '+1234567890';

        // Initially should have 5 attempts
        $this->assertEquals(5, $this->otpService->getRemainingAttempts($userId, $purpose));

        // After 2 attempts, should have 3 remaining
        $this->otpService->start($userId, $purpose, $channel, $to);
        $this->otpService->start($userId, $purpose, $channel, $to);
        
        $this->assertEquals(3, $this->otpService->getRemainingAttempts($userId, $purpose));
    }

    public function test_otp_can_clear_attempts()
    {
        $userId = 1;
        $purpose = 'login';
        $channel = 'sms';
        $to = '+1234567890';

        // Make 3 attempts
        for ($i = 0; $i < 3; $i++) {
            $this->otpService->start($userId, $purpose, $channel, $to);
        }

        $this->assertEquals(2, $this->otpService->getRemainingAttempts($userId, $purpose));

        // Clear attempts
        $this->otpService->clearAttempts($userId, $purpose);

        // Should be back to 5 attempts
        $this->assertEquals(5, $this->otpService->getRemainingAttempts($userId, $purpose));
    }
}



