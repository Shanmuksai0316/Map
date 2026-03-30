<?php

namespace App\Filament\Resources\Admin\StaffManagementResource\Pages;

use App\Filament\Resources\Admin\StaffManagementResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateStaffManagement extends CreateRecord
{
    protected static string $resource = StaffManagementResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set kind to staff
        $data['kind'] = 'staff';
        
        // Set is_map_staff to true (required for StaffManagementResource query)
        $data['is_map_staff'] = true;
        
        // Generate a random password (staff uses OTP login anyway)
        $data['password'] = Hash::make(Str::random(32));
        
        // tenant_id can be null (will appear in Unassigned Staff if null)
        
        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}


