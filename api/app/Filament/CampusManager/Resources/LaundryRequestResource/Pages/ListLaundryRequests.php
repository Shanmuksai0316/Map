<?php

namespace App\Filament\CampusManager\Resources\LaundryRequestResource\Pages;

use App\Filament\CampusManager\Resources\LaundryRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListLaundryRequests extends ListRecords
{
    protected static string $resource = LaundryRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

