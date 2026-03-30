<?php

namespace App\Filament\CollegeMgmt\Resources\StudentResource\Pages;

use App\Filament\CollegeMgmt\Resources\StudentResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStudents extends ListRecords
{
    protected static string $resource = StudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action - read-only
        ];
    }
}
