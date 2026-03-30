<?php

namespace App\Filament\Resources\Admin\StaffUserResource\Pages;

use App\Events\StaffAssignmentChanged;
use App\Events\UserRoleChanged;
use App\Filament\Resources\Admin\StaffUserResource;
use App\Models\Hostel;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;

class CreateStaffUser extends CreateRecord
{
    protected static string $resource = StaffUserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth()->user()->tenant_id;
        $data['kind'] = 'staff';
        
        return $data;
    }

    protected function afterCreate(): void
    {
        $user = $this->record;
        $role = $this->data['role_hint'];
        $hostelIds = $this->data['hostels'] ?? [];

        // Assign role
        if ($role) {
            $user->assignRole($role);
            event(new UserRoleChanged($user->id, auth()->id(), now()->toISOString()));
        }

        // Create hostel assignments
        if (!empty($hostelIds)) {
            $assignments = [];
            foreach ($hostelIds as $hostelId) {
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
            event(new StaffAssignmentChanged($user->id, auth()->id(), now()->toISOString()));
        }
    }
}