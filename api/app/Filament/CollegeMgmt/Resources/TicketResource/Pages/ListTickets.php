<?php

namespace App\Filament\CollegeMgmt\Resources\TicketResource\Pages;

use App\Filament\CollegeMgmt\Resources\TicketResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTickets extends ListRecords
{
    protected static string $resource = TicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action - read-only
        ];
    }
}
