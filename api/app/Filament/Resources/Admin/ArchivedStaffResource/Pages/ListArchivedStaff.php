<?php

namespace App\Filament\Resources\Admin\ArchivedStaffResource\Pages;

use App\Filament\Resources\Admin\ArchivedStaffResource;
use Filament\Resources\Pages\ListRecords;

class ListArchivedStaff extends ListRecords
{
    protected static string $resource = ArchivedStaffResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

