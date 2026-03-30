<?php

use App\Enums\TenantStatus;
use App\Filament\Pages\Admin\TenantOnboardingWizard;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::flush();

    Role::findOrCreate('Super Admin');

    $this->superAdmin = User::factory()->create([
        'tenant_id' => null,
        'name' => 'Super Admin',
        'phone' => '+911234567890',
    ]);
    $this->superAdmin->assignRole('Super Admin');
});

test('tenant onboarding wizard page renders for super admin', function (): void {
    Livewire::actingAs($this->superAdmin)
        ->test(TenantOnboardingWizard::class)
        ->assertSet('tenant', null)
        ->assertSet('data', [
            'tenant_info' => [],
            'hostels' => [],
            'staff' => [],
            'contacts' => [],
        ]);
});

test('non super admin cannot access tenant onboarding wizard', function (): void {
    $nonAdmin = User::factory()->create([
        'tenant_id' => null,
        'name' => 'Regular User',
        'phone' => '+919999999999',
    ]);

    $this->actingAs($nonAdmin, 'web');

    $this->get(route('filament.admin.pages.tenant-onboarding-wizard'))
        ->assertForbidden();
});

test('save draft stores wizard data on tenant', function (): void {
    $tenant = Tenant::factory()->create([
        'status' => TenantStatus::PROVISIONING,
        'data' => [],
    ]);

    $wizardData = [
        'tenant_info' => [
            'name' => 'Academy of Filament',
            'code' => 'MAP-ACADEMY',
        ],
        'hostels' => [
            [
                'name' => 'Hostel A',
                'code' => 'HA',
            ],
        ],
        'staff' => [
            'campus_manager_id' => null,
            'hostel_assignments' => [],
        ],
        'contacts' => [],
    ];

    Livewire::actingAs($this->superAdmin)
        ->test(TenantOnboardingWizard::class)
        ->set('tenant', $tenant)
        ->set('data', $wizardData)
        ->call('saveDraft');

    $payload = DB::table('tenants')->where('id', $tenant->id)->value('data');
    $payload = $payload ? json_decode($payload, true) : [];

    expect($payload['wizard']['tenant_info']['name'])->toBe('Academy of Filament');
    expect($payload['wizard']['hostels'][0]['code'])->toBe('HA');
});
