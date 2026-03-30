<?php

namespace App\Filament\CampusManager\Resources\GuestVisitResource\Pages;

use App\Filament\CampusManager\Resources\GuestVisitResource;
use Filament\Resources\Pages\ListRecords;

class ListGuestVisits extends ListRecords
{
    protected static string $resource = GuestVisitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action - read only
        ];
    }
}

