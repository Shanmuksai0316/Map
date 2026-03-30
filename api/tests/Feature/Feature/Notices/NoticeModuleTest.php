<?php

declare(strict_types=1);

use App\Models\Notice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config(['features.notices_module' => true]);
    Role::findOrCreate('Campus Manager');
    Role::findOrCreate('Rector');
    Role::findOrCreate('Super Admin');
});

function actAsNoticeManager(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->campusManager()->create(['tenant_id' => $tenant->id]);
    $user->assignRole('Campus Manager');
    Sanctum::actingAs($user);

    return compact('tenant', 'user');
}

it('creates notice draft', function (): void {
    $context = actAsNoticeManager();

    $payload = [
        'title' => 'Urgent Water Maintenance',
        'body' => 'Water supply will be off tonight from 10 PM to 2 AM.',
        'audience' => 'all_students',
    ];

    $response = $this->postJson('/api/v1/notices', $payload);

    $response->assertCreated();
    expect(Notice::query()->where('tenant_id', $context['tenant']->id)->count())->toBe(1);
});

it('publishes notice', function (): void {
    $context = actAsNoticeManager();

    $notice = Notice::factory()->create(['tenant_id' => $context['tenant']->id]);

    $this->postJson("/api/v1/notices/{$notice->id}/publish")
        ->assertAccepted();

    expect($notice->fresh()->status)->toBe('published');
});

it('prevents cross-tenant access', function (): void {
    actAsNoticeManager();

    $otherNotice = Notice::factory()->create();

    $this->postJson("/api/v1/notices/{$otherNotice->id}/publish")
        ->assertForbidden();
});

it('returns 404 when feature disabled', function (): void {
    actAsNoticeManager();
    config(['features.notices_module' => false]);

    $this->getJson('/api/v1/notices')->assertNotFound();
});
