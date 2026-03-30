<?php

namespace App\Filament\CampusManager\Resources\TicketResource\Pages;

use App\Filament\CampusManager\Resources\TicketResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTickets extends ListRecords
{
    protected static string $resource = TicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action - tickets are created via API
        ];
    }
}







