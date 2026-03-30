<?php

declare(strict_types=1);

use App\Models\LaundryRequest;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['features.laundry_module' => true]);
    Role::findOrCreate('Campus Manager');
    Role::findOrCreate('Laundry Staff');
});

function actingAsLaundryManager(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->campusManager()->create(['tenant_id' => $tenant->id]);
    $user->assignRole('Campus Manager');
    Sanctum::actingAs($user);

    return compact('tenant', 'user');
}

it('creates laundry request', function (): void {
    $context = actingAsLaundryManager();

    $student = Student::factory()->create(['tenant_id' => $context['tenant']->id]);

    $payload = [
        'student_id' => $student->id,
        'service_type' => 'wash_only',
        'bag_count' => 2,
    ];

    $response = $this->postJson('/api/v1/laundry/requests', $payload);

    $response->assertCreated();
    expect(LaundryRequest::query()->where('tenant_id', $context['tenant']->id)->count())->toBe(1);
});

it('updates laundry status', function (): void {
    $context = actingAsLaundryManager();

    $request = LaundryRequest::factory()->create(['tenant_id' => $context['tenant']->id]);

    $payload = [
        'status' => 'scheduled',
        'metadata' => ['operator' => 'StaffA'],
    ];

    $response = $this->putJson("/api/v1/laundry/requests/{$request->id}/status", $payload);

    $response->assertStatus(200);
    expect($request->fresh()->status->value)->toBe('scheduled');
});

it('blocks laundry endpoints when feature disabled', function (): void {
    $context = actingAsLaundryManager();
    config(['features.laundry_module' => false]);

    $this->getJson('/api/v1/laundry/requests')->assertNotFound();

    $student = Student::factory()->create(['tenant_id' => $context['tenant']->id]);

    $this->postJson('/api/v1/laundry/requests', [
        'student_id' => $student->id,
        'service_type' => 'wash_only',
        'bag_count' => 1,
    ])->assertNotFound();
});

it('calculates price correctly', function (): void {
    $context = actingAsLaundryManager();

    $student = Student::factory()->create(['tenant_id' => $context['tenant']->id]);

    $response = $this->postJson('/api/v1/laundry/requests', [
        'student_id' => $student->id,
        'service_type' => 'wash_and_iron',
        'bag_count' => 2,
        'weight_kg' => 8.0,
    ]);

    $response->assertCreated();

    $request = LaundryRequest::first();

    // Base price 50 * 2 bags * 1.5 multiplier = 150 (weight_kg not set in request)
    expect($request->calculatePrice())->toBe(150.0);
});

it('requires manual verification for ready status', function (): void {
    $context = actingAsLaundryManager();

    $request = LaundryRequest::factory()->create([
        'tenant_id' => $context['tenant']->id,
        'status' => 'ready',
    ]);

    expect($request->requiresManualVerify())->toBeTrue();

    // Test manual verification
    $success = $request->manualVerify('Items verified and in good condition');
    expect($success)->toBeTrue();
    expect($request->fresh()->status->value)->toBe('delivered');
    expect($request->fresh()->manual_verify_notes)->toBe('Items verified and in good condition');
});

it('handles payment lifecycle', function (): void {
    $context = actingAsLaundryManager();

    $request = LaundryRequest::factory()->create([
        'tenant_id' => $context['tenant']->id,
        'payment_status' => null,
    ]);

    // Check payment is required
    expect($request->requiresPayment())->toBeTrue();

    // Initiate payment
    $response = $this->postJson("/api/v1/laundry/requests/{$request->id}/payment/initiate");
    $response->assertStatus(200);

    $request->refresh();
    expect($request->payment_status)->toBe('pending');
    expect((float)$request->payment_amount)->toBe($request->calculatePrice());

    // Complete payment
    $response = $this->postJson("/api/v1/laundry/requests/{$request->id}/payment/complete", [
        'payment_method' => 'razorpay',
        'payment_reference' => 'rzp_test_123',
        'amount_paid' => $request->calculatePrice(),
    ]);

    $response->assertStatus(200);

    $request->refresh();
    expect($request->payment_status)->toBe('completed');
    expect($request->payment_method)->toBe('razorpay');
    expect($request->payment_reference)->toBe('rzp_test_123');
});

it('prevents payment completion with wrong amount', function (): void {
    $context = actingAsLaundryManager();

    $request = LaundryRequest::factory()->create([
        'tenant_id' => $context['tenant']->id,
        'service_type' => 'dry_clean',
        'bag_count' => 3,
        'payment_status' => 'pending',
        'payment_amount' => 300.0, // 50 * 3 * 2.0 * (5kg / 5kg) = 300
    ]);

    $response = $this->postJson("/api/v1/laundry/requests/{$request->id}/payment/complete", [
        'payment_method' => 'razorpay',
        'payment_reference' => 'rzp_test_123',
        'amount_paid' => 50.0, // Wrong amount
    ]);

    $response->assertStatus(422);
    expect($response->json('detail'))->toContain('Expected amount: 300');
});
