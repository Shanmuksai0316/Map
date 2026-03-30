<?php

namespace App\Filament\CampusManager\Resources\AssignedRoomResource\Pages;

use App\Filament\CampusManager\Resources\AssignedRoomResource;
use Filament\Resources\Pages\ListRecords;

class ListAssignedRooms extends ListRecords
{
    protected static string $resource = AssignedRoomResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

