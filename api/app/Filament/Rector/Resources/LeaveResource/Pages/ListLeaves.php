<?php

namespace App\Filament\Rector\Resources\LeaveResource\Pages;

use App\Filament\Rector\Resources\LeaveResource;
use Filament\Resources\Pages\ListRecords;

class ListLeaves extends ListRecords
{
    protected static string $resource = LeaveResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
