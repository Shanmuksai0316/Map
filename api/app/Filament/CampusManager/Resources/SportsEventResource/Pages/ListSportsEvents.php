<?php

namespace App\Filament\CampusManager\Resources\SportsEventResource\Pages;

use App\Filament\CampusManager\Resources\SportsEventResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSportsEvents extends ListRecords
{
    protected static string $resource = SportsEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

