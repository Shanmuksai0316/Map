<?php

namespace App\Filament\Resources\Admin\UnassignedStaffResource\Pages;

use App\Filament\Resources\Admin\UnassignedStaffResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUnassignedStaff extends ListRecords
{
    protected static string $resource = UnassignedStaffResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Add New Staff')
                ->icon('heroicon-o-user-plus'),
        ];
    }
}

