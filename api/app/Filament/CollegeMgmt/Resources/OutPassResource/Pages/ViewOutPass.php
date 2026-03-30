<?php

namespace App\Filament\CollegeMgmt\Resources\OutPassResource\Pages;

use App\Filament\CollegeMgmt\Resources\OutPassResource;
use Filament\Resources\Pages\ViewRecord;

class ViewOutPass extends ViewRecord
{
    protected static string $resource = OutPassResource::class;

    protected function getHeaderActions(): array
    {
        // Read-only - no actions
        return [];
    }
}
