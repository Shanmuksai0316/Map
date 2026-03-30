<?php

namespace App\Filament\Resources\Admin\AssignedStaffResource\Pages;

use App\Filament\Resources\Admin\AssignedStaffResource;
use Filament\Resources\Pages\ListRecords;

class ListAssignedStaff extends ListRecords
{
    protected static string $resource = AssignedStaffResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

