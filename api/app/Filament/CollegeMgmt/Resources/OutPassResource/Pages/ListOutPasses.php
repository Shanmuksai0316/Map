<?php

namespace App\Filament\CollegeMgmt\Resources\OutPassResource\Pages;

use App\Filament\CollegeMgmt\Resources\OutPassResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListOutPasses extends ListRecords
{
    protected static string $resource = OutPassResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action - read-only
        ];
    }
}
