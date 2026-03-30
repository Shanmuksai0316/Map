<?php

namespace App\Filament\CampusManager\Resources\UnassignedStudentResource\Pages;

use App\Filament\CampusManager\Resources\UnassignedStudentResource;
use Filament\Resources\Pages\ListRecords;

class ListUnassignedStudents extends ListRecords
{
    protected static string $resource = UnassignedStudentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

