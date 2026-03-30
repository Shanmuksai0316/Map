<?php

namespace Tests\Feature\MobileP0;

use App\Enums\TenantStatus;
use App\Models\Hostel;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileTenantLookupTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_tenant_details_for_student_phone(): void
    {
        $tenant = Tenant::factory()->create();
        $tenant->domains()->create(['domain' => $tenant->code . '.mapservices.in']);

        $hostel = Hostel::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'phone' => '+911234567890',
            'kind' => 'Student',
        ]);

        Student::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'hostel_id' => $hostel->id,
        ]);

        $response = $this->postJson('/api/v1/mobile/auth/tenant-lookup', [
            'phone' => $user->phone,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'tenant_id' => (string) $tenant->id,
                    'tenant_code' => $tenant->code,
                    'hostel_id' => $hostel->id,
                    'hostel_name' => $hostel->name,
                ],
            ]);
    }

    public function test_it_returns_404_when_student_not_found(): void
    {
        $response = $this->postJson('/api/v1/mobile/auth/tenant-lookup', [
            'phone' => '+919999999999',
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'errors' => [
                    'status' => 404,
                    'code' => 'USER_NOT_FOUND',
                ],
            ]);
    }

    public function test_it_returns_403_when_tenant_suspended(): void
    {
        $tenant = Tenant::factory()->create([
            'status' => TenantStatus::SUSPENDED,
        ]);

        $tenant->domains()->create(['domain' => $tenant->code . '.mapservices.in']);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'phone' => '+918888888888',
            'kind' => 'Student',
        ]);

        $response = $this->postJson('/api/v1/mobile/auth/tenant-lookup', [
            'phone' => $user->phone,
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'errors' => [
                    'status' => 403,
                    'code' => 'TENANT_SUSPENDED',
                ],
            ]);
    }
}

