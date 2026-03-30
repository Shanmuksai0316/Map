<?php

namespace App\Filament\Resources\Admin\AssignedStaffResource\Pages;

use App\Events\UserRoleChanged;
use App\Filament\Resources\Admin\AssignedStaffResource;
use Filament\Resources\Pages\EditRecord;

class EditAssignedStaff extends EditRecord
{
    protected static string $resource = AssignedStaffResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load current role
        $user = $this->record;
        $data['role_hint'] = $user->roles->first()?->name;
        
        return $data;
    }

    protected function afterSave(): void
    {
        $user = $this->record;
        $newRole = $this->data['role_hint'] ?? null;

        // Handle role change
        $currentRole = $user->roles->first()?->name;
        if ($currentRole !== $newRole) {
            if ($currentRole) {
                $user->removeRole($currentRole);
            }
            if ($newRole) {
                $user->assignRole($newRole);
            }
            event(new UserRoleChanged($user->id, auth()->id(), now()->toISOString()));
        }
    }
}

