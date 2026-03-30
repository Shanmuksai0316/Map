<?php

declare(strict_types=1);

use App\Filament\CampusManager\Resources\RoomResource;
use App\Filament\CampusManager\Resources\RoomResource\Pages\CreateRoom;
use App\Models\Campus;
use App\Models\Hostel;
use App\Models\Room;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function authenticateCampusManager(): array
{
    $tenant = Tenant::factory()->create();
    $campus = Campus::factory()->create(['tenant_id' => $tenant->id]);
    $hostel = Hostel::factory()->create(['tenant_id' => $tenant->id, 'campus_id' => $campus->id]);

    $manager = User::factory()->campusManager()->create(['tenant_id' => $tenant->id]);
    Role::findOrCreate('Campus Manager');
    $manager->assignRole('Campus Manager');

    Livewire::actingAs($manager);

    return compact('tenant', 'campus', 'hostel', 'manager');
}

it('lists rooms scoped to tenant', function (): void {
    $context = authenticateCampusManager();

    $ownedRoom = Room::factory()->create([
        'tenant_id' => $context['tenant']->id,
        'campus_id' => $context['campus']->id,
        'hostel_id' => $context['hostel']->id,
        'number' => '101',
    ]);

    Room::factory()->create(['number' => '202']);

    $visibleIds = RoomResource::getEloquentQuery()->pluck('id')->all();

    expect($visibleIds)
        ->toContain($ownedRoom->id)
        ->not->toContain(Room::query()->where('number', '202')->value('id'));
});

