<?php

namespace App\Filament\CampusManager\Resources\UnassignedRoomResource\Pages;

use App\Filament\CampusManager\Resources\UnassignedRoomResource;
use Filament\Resources\Pages\ListRecords;

class ListUnassignedRooms extends ListRecords
{
    protected static string $resource = UnassignedRoomResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

