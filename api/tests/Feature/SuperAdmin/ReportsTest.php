<?php

namespace Tests\Feature\SuperAdmin;

use App\Jobs\GenerateReport;
use App\Models\Tenant;
use App\Models\User;
use App\Support\HostelScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * @group slow
 */
class ReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        Role::findOrCreate('Super Admin', 'web');
    }

    public function test_super_admin_can_generate_report(): void
    {
        Queue::fake();
        
        $tenant = Tenant::factory()->create();
        $superAdmin = User::factory()->create(['tenant_id' => $tenant->id]);
        $superAdmin->assignRole('Super Admin');
        
        $this->actingAs($superAdmin);
        
        $response = $this->postJson('/api/v1/reports', [
            'report_name' => 'hostel_performance',
            'from_date' => now()->subDays(30)->format('Y-m-d'),
            'to_date' => now()->format('Y-m-d'),
            'hostel_id' => null,
        ]);
        
        $response->assertStatus(201);
        
        Queue::assertPushed(GenerateReport::class);
        
        $this->assertDatabaseHas('reports', [
            'tenant_id' => $tenant->id,
            'name' => 'hostel_performance',
            'status' => 'queued',
        ]);
    }

    public function test_report_generation_respects_date_range_limit(): void
    {
        $tenant = Tenant::factory()->create();
        $superAdmin = User::factory()->create(['tenant_id' => $tenant->id]);
        $superAdmin->assignRole('Super Admin');
        
        $this->actingAs($superAdmin);
        
        $response = $this->postJson('/api/v1/reports', [
            'report_name' => 'hostel_performance',
            'from_date' => now()->subDays(100)->format('Y-m-d'),
            'to_date' => now()->format('Y-m-d'),
            'hostel_id' => null,
        ]);
        
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['from_date']);
    }

    public function test_report_generation_respects_hostel_scope(): void
    {
        $tenant = Tenant::factory()->create();
        $superAdmin = User::factory()->create(['tenant_id' => $tenant->id]);
        $superAdmin->assignRole('Super Admin');
        
        $this->actingAs($superAdmin);
        
        $response = $this->postJson('/api/v1/reports', [
            'report_name' => 'hostel_performance',
            'from_date' => now()->subDays(30)->format('Y-m-d'),
            'to_date' => now()->format('Y-m-d'),
            'hostel_id' => 999, // Non-existent hostel
        ]);
        
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['hostel_id']);
    }

    public function test_super_admin_can_download_completed_report(): void
    {
        $tenant = Tenant::factory()->create();
        $superAdmin = User::factory()->create(['tenant_id' => $tenant->id]);
        $superAdmin->assignRole('Super Admin');
        
        // Create a completed report
        $report = \DB::table('reports')->insertGetId([
            'tenant_id' => $tenant->id,
            'name' => 'hostel_performance',
            'params' => json_encode(['from_date' => '2024-01-01', 'to_date' => '2024-01-31']),
            'status' => 'done',
            'storage_path' => 'reports/test.csv',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        
        $this->actingAs($superAdmin);
        
        $response = $this->get("/admin/reports/download/{$report}");
        
        $response->assertStatus(200);
        $response->assertHeader('content-disposition');
    }

    public function test_non_super_admin_cannot_access_reports(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        
        $this->actingAs($user);
        
        $response = $this->get('/admin/reports');
        
        $response->assertStatus(403);
    }
}
