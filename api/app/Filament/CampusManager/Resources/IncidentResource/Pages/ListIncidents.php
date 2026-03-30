<?php

namespace App\Filament\CampusManager\Resources\IncidentResource\Pages;

use App\Filament\CampusManager\Resources\IncidentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListIncidents extends ListRecords
{
    protected static string $resource = IncidentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

