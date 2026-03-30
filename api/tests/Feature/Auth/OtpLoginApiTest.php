<?php

namespace Tests\Feature\Auth;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OtpLoginApiTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        // Ensure required roles exist
        foreach (['Student'] as $role) {
            Role::findOrCreate($role);
        }

        $this->tenant = Tenant::factory()->create([
            'status' => TenantStatus::ACTIVE,
        ]);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'phone' => '+919876543210',
            'name' => 'Test Student',
        ]);
        $this->user->assignRole('Student');
    }

    public function test_send_otp_success_sets_rate_limit_counters(): void
    {
        $response = $this->postJson('/api/v1/auth/send-otp', [
            'phone' => $this->user->phone,
            'device_name' => 'Test Device',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.message', 'OTP sent successfully');

        $dailyKey = "otp:daily_sends:{$this->user->id}:" . now()->format('Y-m-d');
        $resendKey = "otp:resend_cooldown:{$this->user->id}";

        $this->assertEquals(1, Cache::get($dailyKey));
        $this->assertTrue(Cache::has($resendKey));
    }

    public function test_send_otp_respects_resend_cooldown(): void
    {
        $first = $this->postJson('/api/v1/auth/send-otp', [
            'phone' => $this->user->phone,
        ]);
        $first->assertOk();

        $second = $this->postJson('/api/v1/auth/send-otp', [
            'phone' => $this->user->phone,
        ]);
        $second->assertStatus(429)
            ->assertJsonPath('errors.code', 'RESEND_COOLDOWN');
    }

    public function test_send_otp_blocks_after_daily_limit(): void
    {
        $dailyKey = "otp:daily_sends:{$this->user->id}:" . now()->format('Y-m-d');
        $resendKey = "otp:resend_cooldown:{$this->user->id}";

        // Send OTP 5 times (allowed). After each send, clear cooldown to simulate waiting 60s
        for ($i = 0; $i < 5; $i++) {
            $response = $this->postJson('/api/v1/auth/send-otp', [
                'phone' => $this->user->phone,
            ]);
            $response->assertOk();

            Cache::forget($resendKey);
        }

        $this->assertEquals(5, Cache::get($dailyKey));

        // Sixth attempt should be blocked
        $blocked = $this->postJson('/api/v1/auth/send-otp', [
            'phone' => $this->user->phone,
        ]);

        $blocked->assertStatus(429)
            ->assertJsonPath('errors.code', 'RATE_LIMIT_EXCEEDED');
    }

    public function test_verify_otp_success_returns_token_and_clears_cache(): void
    {
        Cache::put("otp:login:{$this->user->id}", [
            'hash' => Hash::make('123456'),
            'attempts' => 0,
        ], now()->addMinutes(10));

        $response = $this->postJson('/api/v1/auth/verify-otp', [
            'phone' => $this->user->phone,
            'otp' => '123456',
            'device_name' => 'Test Device',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['data' => ['token', 'user']]);

        $this->assertNull(Cache::get("otp:login:{$this->user->id}"));
    }

    public function test_verify_otp_blocks_after_three_attempts(): void
    {
        Cache::put("otp:login:{$this->user->id}", [
            'hash' => Hash::make('654321'),
            'attempts' => 0,
        ], now()->addMinutes(10));

        // Three invalid attempts
        for ($i = 0; $i < 3; $i++) {
            $response = $this->postJson('/api/v1/auth/verify-otp', [
                'phone' => $this->user->phone,
                'otp' => '000000',
            ]);

            $response->assertStatus(401)
                ->assertJsonPath('errors.code', 'INVALID_OTP');
        }

        // Fourth attempt should report max attempts exceeded
        $blocked = $this->postJson('/api/v1/auth/verify-otp', [
            'phone' => $this->user->phone,
            'otp' => '000000',
        ]);

        $blocked->assertStatus(401)
            ->assertJsonPath('errors.code', 'OTP_MAX_ATTEMPTS');

        $this->assertNull(Cache::get("otp:login:{$this->user->id}"));
    }
}
