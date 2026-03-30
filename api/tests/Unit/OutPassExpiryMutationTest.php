<?php

namespace Tests\Unit;

use App\Enums\OutPassStatus;
use App\Models\Domain\OutPass\OutPass;
use App\Models\Student;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutPassExpiryMutationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that checking out-pass expiry does not mutate the requested_at timestamp
     *
     * @test
     */
    public function it_does_not_mutate_requested_at_when_checking_expiry(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        $student = Student::factory()->create(['tenant_id' => $tenant->id]);
        
        $requestedAt = Carbon::parse('2024-01-01 10:00:00');
        $outPass = OutPass::factory()->create([
            'tenant_id' => $tenant->id,
            'student_id' => $student->id,
            'status' => OutPassStatus::PENDING,
            'requested_at' => $requestedAt->copy(),
        ]);

        $originalTimestamp = $outPass->requested_at->toDateTimeString();
        
        // Act - Call the controller method that checks expiry
        // This uses reflection to test the private method
        $controller = new \App\Http\Controllers\Api\V1\OutPassController(
            app(\App\Services\StepUpOtpService::class)
        );
        
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('isOutPassExpired');
        $method->setAccessible(true);
        
        // Call the method multiple times to ensure no mutation
        $method->invoke($controller, $outPass);
        $method->invoke($controller, $outPass);
        $method->invoke($controller, $outPass);
        
        // Assert - The timestamp should remain unchanged
        $this->assertEquals($originalTimestamp, $outPass->requested_at->toDateTimeString());
        $this->assertEquals($requestedAt->toDateTimeString(), $outPass->requested_at->toDateTimeString());
    }

    /**
     * Test that out-pass correctly identifies expired status
     *
     * @test
     */
    public function it_correctly_identifies_expired_outpass(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        $student = Student::factory()->create(['tenant_id' => $tenant->id]);
        
        // Create an out-pass requested 25 hours ago (should be expired)
        $requestedAt = now()->subHours(25);
        $outPass = OutPass::factory()->create([
            'tenant_id' => $tenant->id,
            'student_id' => $student->id,
            'status' => OutPassStatus::PENDING,
            'requested_at' => $requestedAt,
        ]);

        // Act
        $controller = new \App\Http\Controllers\Api\V1\OutPassController(
            app(\App\Services\StepUpOtpService::class)
        );
        
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('isOutPassExpired');
        $method->setAccessible(true);
        
        $isExpired = $method->invoke($controller, $outPass);
        
        // Assert
        $this->assertTrue($isExpired);
    }

    /**
     * Test that out-pass correctly identifies non-expired status
     *
     * @test
     */
    public function it_correctly_identifies_non_expired_outpass(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        $student = Student::factory()->create(['tenant_id' => $tenant->id]);
        
        // Create an out-pass requested 10 hours ago (should not be expired)
        $requestedAt = now()->subHours(10);
        $outPass = OutPass::factory()->create([
            'tenant_id' => $tenant->id,
            'student_id' => $student->id,
            'status' => OutPassStatus::PENDING,
            'requested_at' => $requestedAt,
        ]);

        // Act
        $controller = new \App\Http\Controllers\Api\V1\OutPassController(
            app(\App\Services\StepUpOtpService::class)
        );
        
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('isOutPassExpired');
        $method->setAccessible(true);
        
        $isExpired = $method->invoke($controller, $outPass);
        
        // Assert
        $this->assertFalse($isExpired);
    }

    /**
     * Test that approved out-passes are never considered expired
     *
     * @test
     */
    public function it_does_not_expire_approved_outpasses(): void
    {
        // Arrange
        $tenant = Tenant::factory()->create();
        $student = Student::factory()->create(['tenant_id' => $tenant->id]);
        
        // Create an out-pass requested 30 hours ago but already approved
        $requestedAt = now()->subHours(30);
        $outPass = OutPass::factory()->create([
            'tenant_id' => $tenant->id,
            'student_id' => $student->id,
            'status' => OutPassStatus::APPROVED,
            'requested_at' => $requestedAt,
        ]);

        // Act
        $controller = new \App\Http\Controllers\Api\V1\OutPassController(
            app(\App\Services\StepUpOtpService::class)
        );
        
        $reflection = new \ReflectionClass($controller);
        $method = $reflection->getMethod('isOutPassExpired');
        $method->setAccessible(true);
        
        $isExpired = $method->invoke($controller, $outPass);
        
        // Assert
        $this->assertFalse($isExpired, 'Approved out-passes should never be marked as expired');
    }
}

