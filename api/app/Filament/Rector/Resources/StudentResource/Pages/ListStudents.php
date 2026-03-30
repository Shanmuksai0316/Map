<?php

namespace App\Filament\Rector\Resources\StudentResource\Pages;

use App\Filament\Rector\Resources\StudentResource;
use Filament\Resources\Pages\ListRecords;

class ListStudents extends ListRecords
{
    protected static string $resource = StudentResource::class;

    protected static ?string $title = 'Students';

    protected function getHeaderActions(): array
    {
        return [
            // No actions - Rector is view-only
        ];
    }
}

