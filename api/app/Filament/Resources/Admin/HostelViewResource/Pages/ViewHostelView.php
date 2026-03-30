<?php

namespace App\Filament\Resources\Admin\HostelViewResource\Pages;

use App\Filament\Resources\Admin\HostelViewResource;
use Filament\Resources\Pages\ViewRecord;

class ViewHostelView extends ViewRecord
{
    protected static string $resource = HostelViewResource::class;
    
    protected function getHeaderActions(): array
    {
        return [
            // No edit/delete actions - read-only
        ];
    }
}

