<?php

namespace Tests\Feature\OutPass;

use App\Enums\OutPassStatus;
use App\Jobs\ExpireOutPassesJob;
use App\Models\Campus;
use App\Models\Domain\OutPass\OutPass;
use App\Models\Hostel;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OutPassExpiryService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OutPassExpiryTest extends TestCase
{
    use RefreshDatabase;

    private function setupOutPassContext(): array
    {
        $tenant = Tenant::factory()->create();
        $campus = Campus::factory()->create(['tenant_id' => $tenant->id]);
        $hostel = Hostel::factory()->create([
            'tenant_id' => $tenant->id,
            'campus_id' => $campus->id,
        ]);

        Role::findOrCreate('Campus Manager');
        $manager = User::factory()->create(['tenant_id' => $tenant->id]);
        $manager->assignRole('Campus Manager');

        Sanctum::actingAs($manager);

        return compact('tenant', 'campus', 'hostel', 'manager');
    }

    public function test_expires_approved_outpasses_past_valid_until(): void
    {
        $context = $this->setupOutPassContext();

        // Create approved outpasses - one expired, one not expired
        $expiredOutPass = OutPass::factory()->create([
            'tenant_id' => $context['tenant']->id,
            'hostel_id' => $context['hostel']->id,
            'status' => OutPassStatus::APPROVED,
            'valid_until' => Carbon::now('Asia/Kolkata')->subHour(), // Expired
        ]);

        $validOutPass = OutPass::factory()->create([
            'tenant_id' => $context['tenant']->id,
            'hostel_id' => $context['hostel']->id,
            'status' => OutPassStatus::APPROVED,
            'valid_until' => Carbon::now('Asia/Kolkata')->addHour(), // Still valid
        ]);

        // Run the expiry service
        $expiryService = app(OutPassExpiryService::class);
        $expiredCount = $expiryService->expireOutPasses();

        $this->assertEquals(1, $expiredCount);

        // Check that expired outpass was marked as expired
        $this->assertEquals(OutPassStatus::EXPIRED, $expiredOutPass->fresh()->status);
        $this->assertEquals(OutPassStatus::APPROVED, $validOutPass->fresh()->status);

        // Check that history was recorded
        $this->assertDatabaseHas('out_pass_histories', [
            'out_pass_id' => $expiredOutPass->id,
            'from_status' => OutPassStatus::APPROVED->value,
            'to_status' => OutPassStatus::EXPIRED->value,
            'note' => 'Out-pass expired automatically',
        ]);
    }

    public function test_does_not_expire_non_approved_outpasses(): void
    {
        $context = $this->setupOutPassContext();

        // Create non-approved outpasses with past valid_until
        $pendingOutPass = OutPass::factory()->create([
            'tenant_id' => $context['tenant']->id,
            'hostel_id' => $context['hostel']->id,
            'status' => OutPassStatus::PENDING,
            'valid_until' => Carbon::now('Asia/Kolkata')->subHour(),
        ]);

        $declinedOutPass = OutPass::factory()->create([
            'tenant_id' => $context['tenant']->id,
            'hostel_id' => $context['hostel']->id,
            'status' => OutPassStatus::DECLINED,
            'valid_until' => Carbon::now('Asia/Kolkata')->subHour(),
        ]);

        // Run the expiry service
        $expiryService = app(OutPassExpiryService::class);
        $expiredCount = $expiryService->expireOutPasses();

        $this->assertEquals(0, $expiredCount);

        // Check that outpasses were not changed
        $this->assertEquals(OutPassStatus::PENDING, $pendingOutPass->fresh()->status);
        $this->assertEquals(OutPassStatus::DECLINED, $declinedOutPass->fresh()->status);
    }

    public function test_expiry_job_dispatches_correctly(): void
    {
        Queue::fake();

        // Dispatch the expiry job
        ExpireOutPassesJob::dispatch();

        Queue::assertPushed(ExpireOutPassesJob::class);
    }

    public function test_get_expiring_outpasses(): void
    {
        $context = $this->setupOutPassContext();

        // Create outpasses with different expiry times
        $expiringSoon = OutPass::factory()->create([
            'tenant_id' => $context['tenant']->id,
            'hostel_id' => $context['hostel']->id,
            'status' => OutPassStatus::APPROVED,
            'valid_until' => Carbon::now('Asia/Kolkata')->addMinutes(15), // Expires in 15 minutes
        ]);

        $expiringLater = OutPass::factory()->create([
            'tenant_id' => $context['tenant']->id,
            'hostel_id' => $context['hostel']->id,
            'status' => OutPassStatus::APPROVED,
            'valid_until' => Carbon::now('Asia/Kolkata')->addHour(), // Expires in 1 hour
        ]);

        // Get outpasses expiring within 30 minutes
        $expiryService = app(OutPassExpiryService::class);
        $expiringOutPasses = $expiryService->getExpiringOutPasses(30);

        $this->assertCount(1, $expiringOutPasses);
        $this->assertEquals($expiringSoon->id, $expiringOutPasses->first()->id);
    }

    public function test_expiry_status_calculation(): void
    {
        $context = $this->setupOutPassContext();

        // Create outpass with future expiry
        $outPass = OutPass::factory()->create([
            'tenant_id' => $context['tenant']->id,
            'hostel_id' => $context['hostel']->id,
            'status' => OutPassStatus::APPROVED,
            'valid_until' => Carbon::now('Asia/Kolkata')->addMinutes(45),
        ]);

        $expiryService = app(OutPassExpiryService::class);
        $status = $expiryService->getExpiryStatus($outPass);

        $this->assertFalse($status['is_expired']);
        $this->assertEquals('valid', $status['status']);
        $this->assertGreaterThan(30, $status['expires_in_minutes']);
        $this->assertLessThanOrEqual(45, $status['expires_in_minutes']);

        // Test expiring soon status
        $expiringSoonOutPass = OutPass::factory()->create([
            'tenant_id' => $context['tenant']->id,
            'hostel_id' => $context['hostel']->id,
            'status' => OutPassStatus::APPROVED,
            'valid_until' => Carbon::now('Asia/Kolkata')->addMinutes(15),
        ]);

        $status = $expiryService->getExpiryStatus($expiringSoonOutPass);
        $this->assertEquals('expiring_soon', $status['status']);

        // Test expired status
        $expiredOutPass = OutPass::factory()->create([
            'tenant_id' => $context['tenant']->id,
            'hostel_id' => $context['hostel']->id,
            'status' => OutPassStatus::APPROVED,
            'valid_until' => Carbon::now('Asia/Kolkata')->subHour(),
        ]);

        $status = $expiryService->getExpiryStatus($expiredOutPass);
        $this->assertTrue($status['is_expired']);
        $this->assertEquals('expired', $status['status']);
        $this->assertEquals(0, $status['expires_in_minutes']);
    }

    public function test_is_expired_check(): void
    {
        $context = $this->setupOutPassContext();

        $expiryService = app(OutPassExpiryService::class);

        // Test expired outpass
        $expiredOutPass = OutPass::factory()->create([
            'tenant_id' => $context['tenant']->id,
            'hostel_id' => $context['hostel']->id,
            'status' => OutPassStatus::APPROVED,
            'valid_until' => Carbon::now('Asia/Kolkata')->subHour(),
        ]);

        $this->assertTrue($expiryService->isExpired($expiredOutPass));

        // Test valid outpass
        $validOutPass = OutPass::factory()->create([
            'tenant_id' => $context['tenant']->id,
            'hostel_id' => $context['hostel']->id,
            'status' => OutPassStatus::APPROVED,
            'valid_until' => Carbon::now('Asia/Kolkata')->addHour(),
        ]);

        $this->assertFalse($expiryService->isExpired($validOutPass));

        // Test non-approved outpass (should not be considered expired)
        $pendingOutPass = OutPass::factory()->create([
            'tenant_id' => $context['tenant']->id,
            'hostel_id' => $context['hostel']->id,
            'status' => OutPassStatus::PENDING,
            'valid_until' => Carbon::now('Asia/Kolkata')->subHour(),
        ]);

        $this->assertFalse($expiryService->isExpired($pendingOutPass));
    }

    public function test_expiry_command_runs_synchronously(): void
    {
        $context = $this->setupOutPassContext();

        // Create an expired outpass
        $expiredOutPass = OutPass::factory()->create([
            'tenant_id' => $context['tenant']->id,
            'hostel_id' => $context['hostel']->id,
            'status' => OutPassStatus::APPROVED,
            'valid_until' => Carbon::now('Asia/Kolkata')->subHour(),
        ]);

        // Run the command synchronously
        $this->artisan('outpass:expire', ['--sync' => true])
            ->assertExitCode(0);

        // Check that outpass was expired
        $this->assertEquals(OutPassStatus::EXPIRED, $expiredOutPass->fresh()->status);
    }
}
