<?php

namespace App\Filament\CampusManager\Resources\SportsFacilityResource\Pages;

use App\Filament\CampusManager\Resources\SportsFacilityResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSportsFacilities extends ListRecords
{
    protected static string $resource = SportsFacilityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
