<?php

namespace App\Filament\Resources\Admin\StaffManagementResource\Pages;

use App\Filament\Resources\Admin\StaffManagementResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStaffManagement extends EditRecord
{
    protected static string $resource = StaffManagementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->requiresConfirmation()
                ->modalHeading('Delete Staff Member')
                ->modalDescription('This will soft delete the staff member. Are you sure?'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}


