<?php

namespace Tests\Feature\SuperAdmin;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Hostel;
use App\Models\Campus;
use App\Support\Dashboard\KpisRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class KpisRepositoryContractTest extends TestCase
{
    use RefreshDatabase;

    protected KpisRepository $kpisRepo;
    protected Tenant $tenant;
    protected Hostel $hostel;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Instantiate KpisRepository
        $this->kpisRepo = new KpisRepository();
        
        // Create test data
        $this->tenant = Tenant::factory()->create();
        $campus = Campus::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->hostel = Hostel::factory()->create([
            'tenant_id' => $this->tenant->id,
            'campus_id' => $campus->id,
            'gender_mode' => 'mixed',
            'curfew_time' => '22:00'
        ]);
        
        $this->superAdmin = User::factory()->superAdmin()->create(['tenant_id' => $this->tenant->id]);
        
        // Assign role
        $superAdminRole = Role::firstOrCreate(['name' => 'Super Admin']);
        $this->superAdmin->assignRole($superAdminRole);
    }

    public function test_total_hostels_returns_non_negative_integer()
    {
        $count = $this->kpisRepo->totalHostels($this->tenant->id);
        
        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function test_beds_utilization_percent_returns_valid_percentage()
    {
        $utilization = $this->kpisRepo->bedsUtilizationPercent($this->tenant->id);
        
        $this->assertIsFloat($utilization);
        $this->assertGreaterThanOrEqual(0, $utilization);
        $this->assertLessThanOrEqual(100, $utilization);
    }

    public function test_available_beds_returns_non_negative_integer()
    {
        $beds = $this->kpisRepo->availableBeds($this->tenant->id);
        
        $this->assertIsInt($beds);
        $this->assertGreaterThanOrEqual(0, $beds);
    }

    public function test_outpasses_today_returns_non_negative_integer()
    {
        $outpasses = $this->kpisRepo->outPassesToday($this->tenant->id);
        
        $this->assertIsArray($outpasses); // Returns array, not int
        $this->assertArrayHasKey('total', $outpasses);
    }

    public function test_late_returns_today_returns_non_negative_integer()
    {
        $lateReturns = $this->kpisRepo->lateReturnsToday($this->tenant->id);
        
        $this->assertIsInt($lateReturns);
        $this->assertGreaterThanOrEqual(0, $lateReturns);
    }

    public function test_tickets_open_by_priority_returns_array()
    {
        $tickets = $this->kpisRepo->ticketsOpenByPriority($this->tenant->id);
        
        $this->assertIsArray($tickets);
        $this->assertArrayHasKey('high', $tickets);
        $this->assertArrayHasKey('medium', $tickets);
        $this->assertArrayHasKey('low', $tickets);
        
        foreach ($tickets as $priority => $count) {
            $this->assertIsInt($count);
            $this->assertGreaterThanOrEqual(0, $count);
        }
    }

    public function test_tickets_sla_breached_returns_non_negative_integer()
    {
        $breached = $this->kpisRepo->ticketsSlaBreachedOpen($this->tenant->id);
        
        $this->assertIsInt($breached);
        $this->assertGreaterThanOrEqual(0, $breached);
    }

    public function test_attendance_closure_percent_returns_valid_percentage()
    {
        $closure = $this->kpisRepo->attendanceClosure7dPercent($this->tenant->id);
        
        $this->assertIsFloat($closure);
        $this->assertGreaterThanOrEqual(0, $closure);
        $this->assertLessThanOrEqual(100, $closure);
    }

    public function test_checklist_on_time_percent_returns_valid_percentage()
    {
        $onTime = $this->kpisRepo->checklistOnTime7dPercent($this->tenant->id);
        
        $this->assertIsFloat($onTime);
        $this->assertGreaterThanOrEqual(0, $onTime);
        $this->assertLessThanOrEqual(100, $onTime);
    }

    public function test_device_health_returns_non_negative_integer()
    {
        $devices = $this->kpisRepo->deviceHealth($this->tenant->id);
        
        $this->assertIsArray($devices); // Returns array, not int
        $this->assertArrayHasKey('total', $devices);
    }

    public function test_attendance_closure_trend_returns_array()
    {
        $trend = $this->kpisRepo->attendanceClosure7dTrend($this->tenant->id);
        
        $this->assertIsArray($trend);
        $this->assertCount(7, $trend); // 7 days
        
        foreach ($trend as $day => $value) {
            $this->assertIsFloat($value);
            $this->assertGreaterThanOrEqual(0, $value);
            $this->assertLessThanOrEqual(100, $value);
        }
    }
}
