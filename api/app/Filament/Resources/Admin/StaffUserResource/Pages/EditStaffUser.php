<?php

namespace App\Filament\Resources\Admin\StaffUserResource\Pages;

use App\Events\StaffAssignmentChanged;
use App\Events\UserRoleChanged;
use App\Filament\Resources\Admin\StaffUserResource;
use App\Models\Hostel;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;

class EditStaffUser extends EditRecord
{
    protected static string $resource = StaffUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load current role and hostel assignments
        $user = $this->record;
        $data['role_hint'] = $user->roles->first()?->name;
        $data['hostels'] = $user->staffHostels()->pluck('hostels.id')->toArray();
        
        return $data;
    }

    protected function afterSave(): void
    {
        $user = $this->record;
        $newRole = $this->data['role_hint'];
        $newHostelIds = $this->data['hostels'] ?? [];

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

        // Handle hostel assignment changes
        $currentHostelIds = $user->staffHostels()->pluck('hostels.id')->toArray();
        $hostelsToAdd = array_diff($newHostelIds, $currentHostelIds);
        $hostelsToRemove = array_diff($currentHostelIds, $newHostelIds);

        // Add new assignments
        if (!empty($hostelsToAdd)) {
            $assignments = [];
            foreach ($hostelsToAdd as $hostelId) {
                $assignments[] = [
                    'tenant_id' => $user->tenant_id,
                    'user_id' => $user->id,
                    'hostel_id' => $hostelId,
                    'assigned_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            DB::table('staff_assignments')->insert($assignments);
        }

        // Revoke removed assignments
        if (!empty($hostelsToRemove)) {
            DB::table('staff_assignments')
                ->where('user_id', $user->id)
                ->whereIn('hostel_id', $hostelsToRemove)
                ->update(['revoked_at' => now()]);
        }

        // Fire event if any assignments changed
        if (!empty($hostelsToAdd) || !empty($hostelsToRemove)) {
            event(new StaffAssignmentChanged($user->id, auth()->id(), now()->toISOString()));
        }
    }
}