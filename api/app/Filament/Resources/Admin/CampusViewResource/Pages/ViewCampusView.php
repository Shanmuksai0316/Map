<?php

namespace App\Filament\Resources\Admin\CampusViewResource\Pages;

use App\Filament\Resources\Admin\CampusViewResource;
use Filament\Resources\Pages\ViewRecord;

class ViewCampusView extends ViewRecord
{
    protected static string $resource = CampusViewResource::class;
    
    protected function getHeaderActions(): array
    {
        return [
            // No edit/delete actions - read-only
        ];
    }
}

