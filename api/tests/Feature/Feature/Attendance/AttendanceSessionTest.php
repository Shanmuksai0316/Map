<?php

declare(strict_types=1);

use App\Models\AttendanceSession;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function actingAsCampusManager(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->campusManager()->create(['tenant_id' => $tenant->id]);
    Role::findOrCreate('Campus Manager');
    $user->assignRole('Campus Manager');
    Sanctum::actingAs($user);

    return compact('tenant', 'user');
}

it('creates attendance session', function (): void {
    $context = actingAsCampusManager();
    config(['features.attendance_module' => true]);

    $payload = [
        'name' => 'Night Roll Call',
        'kind' => 'night_check',
        'scheduled_at' => now()->addHour()->toIso8601String(),
    ];

    $response = $this->postJson('/api/v1/attendance/sessions', $payload);

    $response->assertCreated();
    expect(AttendanceSession::query()->where('tenant_id', $context['tenant']->id)->count())->toBe(1);
});

it('marks attendance for student', function (): void {
    $context = actingAsCampusManager();
    config(['features.attendance_module' => true]);

    $session = AttendanceSession::factory()->create([
        'tenant_id' => $context['tenant']->id,
    ]);

    $student = Student::factory()->create(['tenant_id' => $context['tenant']->id]);

    $payload = [
        'status' => 'present',
        'marked_at' => now()->toIso8601String(),
    ];

    $response = $this->postJson("/api/v1/attendance/sessions/{$session->id}/students/{$student->id}/mark", $payload);

    $response->assertCreated();
    expect($session->logs()->where('student_id', $student->id)->count())->toBe(1);
});

it('blocks attendance endpoints when feature disabled', function (): void {
    $context = actingAsCampusManager();
    config(['features.attendance_module' => false]);

    $this->getJson('/api/v1/attendance/sessions')->assertNotFound();

    $this->postJson('/api/v1/attendance/sessions', [
        'name' => 'Roll Call',
        'kind' => 'roll_call',
        'scheduled_at' => now()->addHour()->toIso8601String(),
    ])->assertNotFound();
});
