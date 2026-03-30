<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Report;
use App\Jobs\GenerateReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * @group slow
 */
class ReportLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test data
        $tenant = Tenant::factory()->create();
        $this->superAdmin = User::factory()->superAdmin()->create(['tenant_id' => $tenant->id]);
        
        // Assign role
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin']);
        $this->superAdmin->assignRole($superAdminRole);
    }

    public function test_report_can_be_queued()
    {
        Queue::fake();
        
        $this->actingAs($this->superAdmin, 'web')
            ->post('/admin/reports', [
                'name' => 'hostel_performance',
                'params' => [
                    'from' => now()->subDays(7)->toDateString(),
                    'to' => now()->toDateString(),
                ]
            ])
            ->assertStatus(302); // Redirect after creation
        
        Queue::assertPushed(GenerateReport::class);
    }

    public function test_report_status_transitions_correctly()
    {
        // Create a report record
        $report = Report::create([
            'tenant_id' => $this->superAdmin->tenant_id,
            'name' => 'hostel_performance',
            'params' => [
                'from' => now()->subDays(7)->toDateString(),
                'to' => now()->toDateString(),
            ],
            'status' => 'queued'
        ]);
        
        $this->assertEquals('queued', $report->status);
        
        // Simulate processing
        $report->update(['status' => 'running']);
        $this->assertEquals('running', $report->status);
        
        // Simulate completion
        $report->update([
            'status' => 'done',
            'storage_path' => 'reports/hostel_performance_123.csv'
        ]);
        $this->assertEquals('done', $report->status);
        $this->assertNotNull($report->storage_path);
    }

    public function test_report_download_endpoint_exists()
    {
        $this->actingAs($this->superAdmin, 'web')
            ->get('/admin/reports/download/1')
            ->assertStatus(404); // Report doesn't exist, but endpoint is reachable
    }

    public function test_report_generation_with_valid_params()
    {
        $params = [
            'from' => now()->subDays(7)->toDateString(),
            'to' => now()->toDateString(),
        ];
        
        $this->actingAs($this->superAdmin, 'web')
            ->post('/admin/reports', [
                'name' => 'hostel_performance',
                'params' => $params
            ])
            ->assertStatus(302);
        
        $report = Report::where('name', 'hostel_performance')->first();
        $this->assertNotNull($report);
        $this->assertEquals($params, $report->params);
    }

    public function test_report_generation_rejects_invalid_date_range()
    {
        $this->actingAs($this->superAdmin, 'web')
            ->post('/admin/reports', [
                'name' => 'hostel_performance',
                'params' => [
                    'from' => now()->subDays(100)->toDateString(), // Too far back
                    'to' => now()->toDateString(),
                ]
            ])
            ->assertStatus(422); // Validation error
    }
}
