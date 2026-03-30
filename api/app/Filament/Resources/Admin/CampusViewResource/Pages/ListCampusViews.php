<?php

namespace App\Filament\Resources\Admin\CampusViewResource\Pages;

use App\Filament\Resources\Admin\CampusViewResource;
use Filament\Resources\Pages\ListRecords;

class ListCampusViews extends ListRecords
{
    protected static string $resource = CampusViewResource::class;
    
    protected function getHeaderActions(): array
    {
        return [
            // No create action - read-only
        ];
    }
}

