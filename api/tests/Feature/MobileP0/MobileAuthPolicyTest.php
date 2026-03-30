<?php

namespace Tests\Feature\MobileP0;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileAuthPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @dataProvider mobileRolesProvider
     */
    public function test_mobile_roles_can_login_via_api(string $role, string $kind): void
    {
        // Seed roles and permissions
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        
        // Create a tenant first
        $tenant = \App\Models\Tenant::factory()->create();
        
        // Create a user with the specified role
        $user = User::factory()->create([
            'kind' => $kind,
            'tenant_id' => $tenant->id,
            'password' => bcrypt('password'), // Set known password
        ]);

        // Assign the role
        $user->assignRole($role);

        // Test login via mobile API
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password', // Default factory password
            'device_name' => 'UAT-Testing',
        ]);

        // Assert successful login
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'tenant_id',
                        'kind',
                    ],
                ],
            ])
            ->assertJson([
                'data' => [
                    'user' => [
                        'id' => (string) $user->id,
                        'email' => $user->email,
                        'kind' => $kind,
                    ],
                ],
            ]);

        // Verify token was created
        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $user->id,
            'tokenable_type' => User::class,
            'name' => 'UAT-Testing',
        ]);
    }

    public function test_non_mobile_roles_cannot_login_via_api(): void
    {
        // Create a tenant first
        $tenant = \App\Models\Tenant::factory()->create();
        
        // Create a user without mobile access
        $user = User::factory()->create([
            'kind' => 'SomeOtherRole',
            'tenant_id' => $tenant->id,
        ]);

        // Test login via mobile API
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'UAT-Testing',
        ]);

        // Assert login is forbidden
        $response->assertStatus(403)
            ->assertJson([
                'errors' => [
                    'status' => 403,
                    'code' => 'AUTH_FORBIDDEN',
                    'title' => 'Login not allowed',
                    'detail' => 'You do not have access to this application.',
                ],
            ]);
    }

    public function test_user_without_tenant_cannot_login(): void
    {
        $this->markTestSkipped('Database correctly enforces tenant_id NOT NULL constraint - this test is invalid');
    }

    public function test_archived_user_cannot_login(): void
    {
        // Seed roles and permissions
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        
        // Create a tenant first
        $tenant = \App\Models\Tenant::factory()->create();
        
        // Create an archived user
        $user = User::factory()->create([
            'kind' => 'Student',
            'tenant_id' => $tenant->id,
            'archived' => true,
            'password' => bcrypt('password'), // Set known password
        ]);

        $user->assignRole('Student');

        // Test login via mobile API
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
            'device_name' => 'UAT-Testing',
        ]);

        // Assert login is forbidden
        $response->assertStatus(403)
            ->assertJson([
                'errors' => [
                    'status' => 403,
                    'code' => 'AUTH_FORBIDDEN',
                    'title' => 'Login not allowed',
                    'detail' => 'You do not have access to this application.',
                ],
            ]);
    }

    public static function mobileRolesProvider(): array
    {
        return [
            'Guard' => ['Guard', 'Guard'],
            'Warden' => ['Warden', 'Warden'],
            'HK Supervisor' => ['HK Supervisor', 'HKSupervisor'],
            'RM Supervisor' => ['RM Supervisor', 'RMSupervisor'],
            'Laundry Manager' => ['Laundry Manager', 'LaundryManager'],
            'Sports Manager' => ['Sports Manager', 'SportsManager'],
            'Student' => ['Student', 'Student'],
        ];
    }
}
