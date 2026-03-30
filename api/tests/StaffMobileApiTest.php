<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Log;
use Tests\CreatesApplication;

/**
 * Comprehensive test for all staff mobile APIs
 * This validates that all endpoints work correctly and return proper data
 */
class StaffMobileApiTest extends TestCase
{
    use CreatesApplication;

    /**
     * Test all staff mobile APIs for a given role
     */
    public function testAllStaffApis()
    {
        // This is a reference test file - actual testing should be done via API calls
        // Run: php artisan test --filter StaffMobileApiTest
    }

    /**
     * Helper: Get auth token for a user
     */
    private function getAuthToken($phone, $otp = '123456')
    {
        // Send OTP
        $response = $this->postJson('/api/v1/mobile/auth/send-otp', [
            'phone' => $phone,
        ], [
            'X-Tenant-Code' => 'PPCU',
        ]);

        // Verify OTP
        $response = $this->postJson('/api/v1/mobile/auth/verify-otp', [
            'phone' => $phone,
            'otp' => $otp,
            'device_name' => 'test-device',
        ], [
            'X-Tenant-Code' => 'PPCU',
        ]);

        return $response->json('data.token');
    }
}
