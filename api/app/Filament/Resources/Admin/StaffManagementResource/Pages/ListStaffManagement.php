<?php

namespace App\Filament\Resources\Admin\StaffManagementResource\Pages;

use App\Filament\Resources\Admin\StaffManagementResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStaffManagement extends ListRecords
{
    protected static string $resource = StaffManagementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Create New Staff')
                ->icon('heroicon-o-plus'),
        ];
    }
}


