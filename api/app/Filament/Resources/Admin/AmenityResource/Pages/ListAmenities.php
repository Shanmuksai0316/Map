<?php

namespace App\Filament\Resources\Admin\AmenityResource\Pages;

use App\Filament\Resources\Admin\AmenityResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAmenities extends ListRecords
{
    protected static string $resource = AmenityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

