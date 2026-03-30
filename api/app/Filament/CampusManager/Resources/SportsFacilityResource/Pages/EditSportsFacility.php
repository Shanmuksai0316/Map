<?php

namespace App\Filament\CampusManager\Resources\SportsFacilityResource\Pages;

use App\Filament\CampusManager\Resources\SportsFacilityResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSportsFacility extends EditRecord
{
    protected static string $resource = SportsFacilityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
