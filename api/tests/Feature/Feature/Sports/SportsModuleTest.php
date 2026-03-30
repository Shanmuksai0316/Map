<?php

declare(strict_types=1);

use App\Models\SportsEvent;
use App\Models\SportsEnrollment;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['features.sports_module' => true]);
    Role::findOrCreate('Campus Manager');
    Role::findOrCreate('Sports Coordinator');
});

function actAsSportsManager(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->campusManager()->create(['tenant_id' => $tenant->id]);
    $user->assignRole('Campus Manager');
    Sanctum::actingAs($user);

    return compact('tenant', 'user');
}

it('creates sports event', function (): void {
    $context = actAsSportsManager();

    $payload = [
        'sport' => 'Football',
        'name' => 'Inter Hostel League',
        'scheduled_at' => now()->addDay()->toIso8601String(),
    ];

    $response = $this->postJson('/api/v1/sports/events', $payload);

    $response->assertCreated();
    expect(SportsEvent::query()->where('tenant_id', $context['tenant']->id)->count())->toBe(1);
});

it('enrolls student into sports event', function (): void {
    $context = actAsSportsManager();

    $event = SportsEvent::factory()->create(['tenant_id' => $context['tenant']->id]);
    $student = Student::factory()->create(['tenant_id' => $context['tenant']->id]);

    $payload = [
        'student_id' => $student->id,
    ];

    $response = $this->postJson("/api/v1/sports/events/{$event->id}/enroll", $payload);

    $response->assertAccepted();
    expect(SportsEnrollment::query()->where('sports_event_id', $event->id)->count())->toBe(1);
});


it('returns 404 when sports module disabled', function (): void {
    $context = actAsSportsManager();
    config(['features.sports_module' => false]);

    $this->getJson('/api/v1/sports/events')->assertNotFound();
});
