<?php

declare(strict_types=1);

use App\Filament\CampusManager\Resources\ImportJobResource;
use App\Models\ImportJob;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function actingAsManagerForImports(): array
{
    $tenant = Tenant::factory()->create();
    $manager = User::factory()->campusManager()->create(['tenant_id' => $tenant->id]);
    Role::findOrCreate('Campus Manager');
    $manager->assignRole('Campus Manager');
    Livewire::actingAs($manager);

    return compact('tenant', 'manager');
}

it('shows only tenant import jobs', function (): void {
    $context = actingAsManagerForImports();

    $ownJob = ImportJob::factory()->create([
        'tenant_id' => $context['tenant']->id,
        'kind' => 'students',
        'status' => 'DryRun',
    ]);

    $otherJob = ImportJob::factory()->create();

    $visibleIds = 
        
        
        ImportJobResource::getEloquentQuery()->pluck('id')->all();

    expect($visibleIds)
        ->toContain($ownJob->id)
        ->not->toContain($otherJob->id);
});
