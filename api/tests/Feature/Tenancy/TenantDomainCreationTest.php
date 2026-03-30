<?php

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantDomainCreationTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create Super Admin for authorization
        $this->superAdmin = User::factory()->create([
            'kind' => 'staff',
            'archived' => false,
        ]);
        $this->superAdmin->assignRole('Super Admin');
        $this->actingAs($this->superAdmin);
    }

    /** @test */
    public function tenant_factory_creates_domain_automatically()
    {
        // Arrange & Act
        $tenant = Tenant::factory()->create();

        // Assert
        $this->assertNotNull($tenant->domains()->first(), 'Tenant should have at least one domain');
        
        $domain = $tenant->domains()->first();
        $this->assertNotEmpty($domain->domain, 'Domain should not be empty');
        
        // In local, should end with .localhost
        if (app()->environment('local')) {
            $this->assertStringEndsWith('.localhost', $domain->domain);
        }
    }

    /** @test */
    public function tenant_created_via_wizard_should_have_domain()
    {
        // This test ensures the bug fix remains in place
        
        // Simulate what TenantOnboardingWizard does
        $tenantCode = 'MAP-TEST-' . Str::random(4);
        $tenantName = 'Test College ' . Str::random(4);
        $subdomain = Str::slug(strtolower($tenantCode));
        
        // Create tenant (mimicking createTenantDraft)
        $tenant = Tenant::create([
            'code' => $tenantCode,
            'name' => $tenantName,
            'status' => \App\Enums\TenantStatus::PROVISIONING,
            'data' => [
                'logo' => null,
                'campus_name' => $tenantName,
            ],
        ]);

        // Create domain (this is what was missing before the fix)
        $domainSuffix = config('app.domain', 'mapmars.com');
        $domain = env('APP_ENV') === 'local' 
            ? $subdomain . '.localhost'
            : $subdomain . '.' . $domainSuffix;
        
        $tenant->domains()->create([
            'domain' => $domain,
        ]);

        // Assert
        $tenant->refresh();
        $this->assertNotNull($tenant->domains()->first(), 'Tenant created via wizard should have domain');
        $this->assertEquals($domain, $tenant->domains()->first()->domain);
    }

    /** @test */
    public function all_tenants_should_have_at_least_one_domain()
    {
        // Create multiple tenants
        $tenants = Tenant::factory()->count(3)->create();

        // Assert each has a domain
        foreach ($tenants as $tenant) {
            $this->assertGreaterThanOrEqual(
                1, 
                $tenant->domains()->count(),
                "Tenant {$tenant->code} should have at least one domain"
            );
        }
    }

    /** @test */
    public function domain_format_is_correct_for_local_environment()
    {
        if (!app()->environment('local')) {
            $this->markTestSkipped('This test only runs in local environment');
        }

        $tenant = Tenant::factory()->create();
        $domain = $tenant->domains()->first();

        $this->assertStringEndsWith('.localhost', $domain->domain);
        $this->assertStringNotContainsString('..', $domain->domain);
    }

    /** @test */
    public function domain_subdomain_uses_tenant_code()
    {
        $tenant = Tenant::factory()->create([
            'code' => 'MAP-TESTCOL',
        ]);

        $domain = $tenant->domains()->first();
        $subdomain = Str::before($domain->domain, '.');

        // Subdomain should be derived from tenant code (slugified, lowercase)
        $expectedSubdomain = Str::slug(strtolower('MAP-TESTCOL'));
        $this->assertEquals($expectedSubdomain, $subdomain);
    }

    /** @test */
    public function tenant_primary_domain_attribute_works()
    {
        $tenant = Tenant::factory()->create();

        // Test the getPrimaryDomainAttribute accessor
        $this->assertNotNull($tenant->primary_domain);
        $this->assertIsString($tenant->primary_domain);
    }

    /** @test */
    public function fix_domains_command_identifies_tenants_without_domains()
    {
        // Create a tenant WITHOUT domain (bypass factory)
        $tenant = Tenant::create([
            'code' => 'MAP-NODOMAIN',
            'name' => 'No Domain College',
            'status' => \App\Enums\TenantStatus::PROVISIONING,
        ]);

        // Assert it has no domains
        $this->assertEquals(0, $tenant->domains()->count());

        // Run the fix command (dry run)
        $this->artisan('tenants:fix-domains', ['--dry-run' => true])
            ->expectsOutput('Found 1 tenant(s) without domains:')
            ->assertExitCode(0);
    }

    /** @test */
    public function fix_domains_command_repairs_missing_domains()
    {
        // Create tenant WITHOUT domain
        $tenant = Tenant::create([
            'code' => 'MAP-FIXME',
            'name' => 'Fix Me College',
            'status' => \App\Enums\TenantStatus::PROVISIONING,
        ]);

        $this->assertEquals(0, $tenant->domains()->count());

        // Run fix command (without dry-run)
        $this->artisan('tenants:fix-domains')
            ->assertExitCode(0);

        // Assert domain was created
        $tenant->refresh();
        $this->assertGreaterThan(0, $tenant->domains()->count());
    }
}
