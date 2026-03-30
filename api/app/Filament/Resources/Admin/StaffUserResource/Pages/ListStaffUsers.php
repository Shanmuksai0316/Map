<?php

namespace App\Filament\Resources\Admin\StaffUserResource\Pages;

use App\Filament\Resources\Admin\StaffUserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStaffUsers extends ListRecords
{
    protected static string $resource = StaffUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
