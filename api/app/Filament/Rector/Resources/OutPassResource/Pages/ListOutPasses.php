<?php

namespace App\Filament\Rector\Resources\OutPassResource\Pages;

use App\Filament\Rector\Resources\OutPassResource;
use Filament\Resources\Pages\ListRecords;

class ListOutPasses extends ListRecords
{
    protected static string $resource = OutPassResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
