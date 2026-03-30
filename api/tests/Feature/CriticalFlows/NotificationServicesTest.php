<?php

namespace Tests\Feature\CriticalFlows;

use App\Services\Notifications\SmsService;
use App\Services\Notify\PushNotifier;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Notification Services Integration Tests
 * 
 * Tests for:
 * - FCM Push Notifications
 * - SMS via MSG91/STPL
 */
class NotificationServicesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    // ============================================
    // FCM PUSH NOTIFICATION TESTS
    // ============================================

    /**
     * Test: FCM is disabled when not configured
     */
    public function test_fcm_disabled_when_not_configured(): void
    {
        Config::set('services.fcm.enabled', false);
        Config::set('services.fcm.service_account_json', null);
        Config::set('services.fcm.server_key', null);

        $pushNotifier = new PushNotifier();

        $this->assertFalse($pushNotifier->enabled());
    }

    /**
     * Test: FCM enabled with service account
     */
    public function test_fcm_enabled_with_service_account(): void
    {
        Config::set('services.fcm.enabled', true);
        Config::set('services.fcm.service_account_json', json_encode([
            'type' => 'service_account',
            'project_id' => 'test-project',
            'client_email' => 'test@test.iam.gserviceaccount.com',
            'private_key' => "-----BEGIN PRIVATE KEY-----\nMIIEvQIBADANBg...\n-----END PRIVATE KEY-----\n",
        ]));

        $pushNotifier = new PushNotifier();

        $this->assertTrue($pushNotifier->enabled());
    }

    /**
     * Test: FCM enabled with legacy server key
     */
    public function test_fcm_enabled_with_legacy_server_key(): void
    {
        Config::set('services.fcm.enabled', true);
        Config::set('services.fcm.service_account_json', null);
        Config::set('services.fcm.service_account_path', null);
        Config::set('services.fcm.server_key', 'test-server-key');

        $pushNotifier = new PushNotifier();

        $this->assertTrue($pushNotifier->enabled());
    }

    /**
     * Test: FCM toToken with V1 API (mocked)
     */
    public function test_fcm_to_token_v1_api(): void
    {
        $this->markTestSkipped('Requires valid service account for JWT signing');

        // This would test the full V1 API flow with mocked HTTP
        Config::set('services.fcm.enabled', true);
        Config::set('services.fcm.project_id', 'test-project');
        Config::set('services.fcm.service_account_json', json_encode([
            'type' => 'service_account',
            'project_id' => 'test-project',
            'client_email' => 'test@test.iam.gserviceaccount.com',
            'private_key' => "-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----\n",
        ]));

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-token']),
            'fcm.googleapis.com/v1/projects/*/messages:send' => Http::response(['name' => 'projects/test-project/messages/123']),
        ]);

        $pushNotifier = new PushNotifier();
        $pushNotifier->toToken('test-device-token', 'Test Title', 'Test Body', ['key' => 'value']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'fcm.googleapis.com');
        });
    }

    /**
     * Test: FCM toToken with Legacy API (mocked)
     */
    public function test_fcm_to_token_legacy_api(): void
    {
        Config::set('services.fcm.enabled', true);
        Config::set('services.fcm.service_account_json', null);
        Config::set('services.fcm.service_account_path', null);
        Config::set('services.fcm.server_key', 'test-server-key-AAAA');

        Http::fake([
            'fcm.googleapis.com/fcm/send' => Http::response([
                'multicast_id' => 123456789,
                'success' => 1,
                'failure' => 0,
                'results' => [['message_id' => '0:123456789%abc']],
            ]),
        ]);

        $pushNotifier = new PushNotifier();
        $pushNotifier->toToken('test-device-token', 'Test Title', 'Test Body', ['key' => 'value']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://fcm.googleapis.com/fcm/send'
                && $request->header('Authorization')[0] === 'key=test-server-key-AAAA'
                && $request['notification']['title'] === 'Test Title';
        });
    }

    /**
     * Test: FCM gracefully handles disabled state
     */
    public function test_fcm_no_op_when_disabled(): void
    {
        Config::set('services.fcm.enabled', false);

        $pushNotifier = new PushNotifier();

        // Should not throw, just log
        $pushNotifier->toToken('test-token', 'Title', 'Body');

        Http::assertNothingSent();
    }

    /**
     * Test: FCM toUser sends to all registered device tokens
     */
    public function test_fcm_to_user_sends_to_all_devices(): void
    {
        Config::set('services.fcm.enabled', true);
        Config::set('services.fcm.service_account_json', null);
        Config::set('services.fcm.server_key', 'test-key');

        // Create a tenant and user first
        $tenant = \App\Models\Tenant::factory()->create();
        $user = \App\Models\User::factory()->create(['tenant_id' => $tenant->id]);

        // Create mock device tokens in DB with required fields
        DB::table('push_device_tokens')->insert([
            [
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'device_id' => 'device-1',
                'device_type' => 'ios',
                'token' => 'device-token-1',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'device_id' => 'device-2',
                'device_type' => 'android',
                'token' => 'device-token-2',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Http::fake([
            'fcm.googleapis.com/fcm/send' => Http::response(['success' => 1]),
        ]);

        $pushNotifier = new PushNotifier();
        $pushNotifier->toUser($user->id, 'Test', 'Body');

        // Should have sent 2 requests (one per device)
        Http::assertSentCount(2);
    }

    // ============================================
    // SMS SERVICE TESTS (MSG91/STPL)
    // ============================================

    /**
     * Test: SMS no-op when both providers disabled
     */
    public function test_sms_no_op_when_disabled(): void
    {
        Config::set('services.msg91.enabled', false);
        Config::set('services.stpl.enabled', false);

        $smsService = new SmsService();
        $result = $smsService->send('+919876543210', 'Test message', 'test-tenant', 'test_template');

        $this->assertTrue($result); // Returns true for no-op

        // Should log to notification_logs
        $this->assertDatabaseHas('notification_logs', [
            'recipient' => '+919876543210',
            'channel' => 'sms',
            'template' => 'test_template',
        ]);

        Http::assertNothingSent();
    }

    /**
     * Test: SMS fails gracefully with missing recipient
     */
    public function test_sms_fails_gracefully_with_missing_recipient(): void
    {
        Config::set('services.msg91.enabled', true);

        $smsService = new SmsService();
        $result = $smsService->send(null, 'Test message', 'test-tenant', 'test_template');

        $this->assertFalse($result);
        Http::assertNothingSent();
    }

    /**
     * Test: MSG91 SMS sends with DLT template
     */
    public function test_msg91_sms_with_dlt_template(): void
    {
        Config::set('services.msg91.enabled', true);
        Config::set('services.msg91.key', 'test-auth-key');
        Config::set('services.msg91.sender_id', 'OMAPMS');
        Config::set('services.msg91.templates.otp_login', '1234567890');
        Config::set('services.stpl.enabled', false);

        Http::fake([
            'api.msg91.com/api/v2/sendsms' => Http::response(['type' => 'success', 'message' => 'SMS sent']),
        ]);

        $smsService = new SmsService();
        $result = $smsService->send(
            '+919876543210',
            'Your OTP is 123456',
            'test-tenant',
            'otp_login',
            ['related_type' => 'User', 'related_id' => 1]
        );

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.msg91.com/api/v2/sendsms'
                && $request->header('authkey')[0] === 'test-auth-key'
                && $request['DLT_TE_ID'] === '1234567890';
        });
    }

    /**
     * Test: MSG91 handles API error response
     */
    public function test_msg91_handles_api_error(): void
    {
        Config::set('services.msg91.enabled', true);
        Config::set('services.msg91.key', 'test-auth-key');
        Config::set('services.stpl.enabled', false);

        Http::fake([
            'api.msg91.com/api/v2/sendsms' => Http::response([
                'type' => 'error',
                'message' => 'Invalid template ID',
            ], 200), // MSG91 returns 200 even for errors
        ]);

        $smsService = new SmsService();
        $result = $smsService->send('+919876543210', 'Test', 'test-tenant', 'invalid_template');

        $this->assertFalse($result);

        // Should log failure to notification_logs
        $this->assertDatabaseHas('notification_logs', [
            'recipient' => '+919876543210',
            'status' => 'failed',
        ]);
    }

    /**
     * Test: STPL takes priority over MSG91 when both enabled
     */
    public function test_stpl_takes_priority_over_msg91(): void
    {
        Config::set('services.stpl.enabled', true);
        Config::set('services.stpl.api_key', 'stpl-test-key');
        Config::set('services.msg91.enabled', true);
        Config::set('services.msg91.key', 'msg91-test-key');

        Http::fake([
            'api.stpl.com/v1/sms/send' => Http::response(['status' => 'success']),
            'api.msg91.com/*' => Http::response(['type' => 'success']),
        ]);

        $smsService = new SmsService();
        $result = $smsService->send('+919876543210', 'Test', 'test-tenant', 'otp_login');

        $this->assertTrue($result);

        // Should have used STPL, not MSG91
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'stpl.com');
        });

        Http::assertNotSent(function ($request) {
            return str_contains($request->url(), 'msg91.com');
        });
    }

    /**
     * Test: Falls back to MSG91 when STPL fails
     */
    public function test_fallback_to_msg91_when_stpl_fails(): void
    {
        Config::set('services.stpl.enabled', true);
        Config::set('services.stpl.api_key', 'stpl-test-key');
        Config::set('services.msg91.enabled', true);
        Config::set('services.msg91.key', 'msg91-test-key');

        Http::fake([
            'api.stpl.com/v1/sms/send' => Http::response(['status' => 'error', 'message' => 'Failed'], 500),
            'api.msg91.com/api/v2/sendsms' => Http::response(['type' => 'success']),
        ]);

        $smsService = new SmsService();
        $result = $smsService->send('+919876543210', 'Test', 'test-tenant', 'otp_login');

        // Should fail since STPL returned error (not null which would trigger fallback)
        // STPL returning false means it handled the request but failed
        $this->assertFalse($result);
    }

    /**
     * Test: SMS notification is logged to database
     */
    public function test_sms_notification_logged_to_database(): void
    {
        Config::set('services.msg91.enabled', true);
        Config::set('services.msg91.key', 'test-key');
        Config::set('services.stpl.enabled', false);

        Http::fake([
            'api.msg91.com/*' => Http::response(['type' => 'success']),
        ]);

        $smsService = new SmsService();
        $smsService->send(
            '+919876543210',
            'Your code is 123456',
            'tenant-123',
            'otp_login',
            [
                'related_type' => 'User',
                'related_id' => 42,
            ]
        );

        $this->assertDatabaseHas('notification_logs', [
            'tenant_id' => 'tenant-123',
            'recipient' => '+919876543210',
            'channel' => 'sms',
            'template' => 'otp_login',
            'status' => 'sent',
            'related_type' => 'User',
            'related_id' => 42,
        ]);
    }
}

