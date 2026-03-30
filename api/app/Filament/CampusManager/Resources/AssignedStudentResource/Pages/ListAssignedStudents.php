<?php

namespace App\Filament\CampusManager\Resources\AssignedStudentResource\Pages;

use App\Filament\CampusManager\Resources\AssignedStudentResource;
use Filament\Resources\Pages\ListRecords;

class ListAssignedStudents extends ListRecords
{
    protected static string $resource = AssignedStudentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

