<?php

use App\Enums\LaundryRequestStatus;
use App\Enums\LaundryServiceType;
use App\Models\Campus;
use App\Models\Hostel;
use App\Models\LaundryRequest;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->campus = Campus::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->hostel = Hostel::factory()->create([
        'tenant_id' => $this->tenant->id,
        'campus_id' => $this->campus->id,
    ]);

    $studentUser = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'kind' => 'student',
    ]);

    $this->student = Student::factory()->create([
        'tenant_id' => $this->tenant->id,
        'hostel_id' => $this->hostel->id,
        'user_id' => $studentUser->id,
    ]);

    $this->manager = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'kind' => 'laundrymanager',
    ]);
    $webRole = Role::firstOrCreate(['name' => 'Laundry Manager', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Laundry Manager', 'guard_name' => 'sanctum']);
    $this->manager->assignRole($webRole);
});

it('completes ready request via pickup code verification', function () {
    $request = LaundryRequest::create([
        'tenant_id' => $this->tenant->id,
        'campus_id' => $this->campus->id,
        'hostel_id' => $this->hostel->id,
        'student_id' => $this->student->id,
        'service_type' => LaundryServiceType::STANDARD,
        'status' => LaundryRequestStatus::READY,
        'bag_count' => 1,
        'pickup_code' => '1234',
        'ready_at' => now()->subHour(),
        'requested_at' => now()->subHours(8),
    ]);

    Sanctum::actingAs($this->manager, ['*']);
    $response = $this->postJson("/api/v1/mobile/laundry/requests/{$request->id}/verify-code", [
        'pickup_code' => '1234',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'completed');

    $request->refresh();
    expect($request->status->value)->toBe('completed');
});

it('supports manual verify fallback endpoint for ready request', function () {
    $request = LaundryRequest::create([
        'tenant_id' => $this->tenant->id,
        'campus_id' => $this->campus->id,
        'hostel_id' => $this->hostel->id,
        'student_id' => $this->student->id,
        'service_type' => LaundryServiceType::STANDARD,
        'status' => LaundryRequestStatus::READY,
        'bag_count' => 1,
        'pickup_code' => '5678',
        'ready_at' => now()->subMinutes(20),
        'requested_at' => now()->subHours(4),
    ]);

    Sanctum::actingAs($this->manager, ['*']);
    $response = $this->postJson("/api/v1/mobile/laundry/requests/{$request->id}/manual-verify", [
        'verify_notes' => 'Student ID card checked at counter.',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.status', 'completed');

    $request->refresh();
    expect($request->manual_verify_notes)->not->toBeNull();
    expect($request->status->value)->toBe('completed');
});
