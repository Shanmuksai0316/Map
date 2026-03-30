<?php

namespace App\Filament\Resources\Admin\RequestResource\Pages;

use App\Filament\Resources\Admin\RequestResource;
use Filament\Resources\Pages\ListRecords;

class ListRequests extends ListRecords
{
    protected static string $resource = RequestResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

