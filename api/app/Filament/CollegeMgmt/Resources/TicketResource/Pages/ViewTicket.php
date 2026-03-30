<?php

namespace App\Filament\CollegeMgmt\Resources\TicketResource\Pages;

use App\Filament\CollegeMgmt\Resources\TicketResource;
use Filament\Resources\Pages\ViewRecord;

class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

    protected function getHeaderActions(): array
    {
        // Read-only - no actions
        return [];
    }
}
