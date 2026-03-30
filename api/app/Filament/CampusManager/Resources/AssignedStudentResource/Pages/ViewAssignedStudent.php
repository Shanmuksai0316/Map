<?php

namespace App\Filament\CampusManager\Resources\AssignedStudentResource\Pages;

use App\Filament\CampusManager\Resources\AssignedStudentResource;
use App\Filament\CampusManager\Resources\StudentResource\Pages\ViewStudent;
use Filament\Actions;

class ViewAssignedStudent extends ViewStudent
{
    protected static string $resource = AssignedStudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

