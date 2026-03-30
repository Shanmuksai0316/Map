<?php

namespace App\Filament\CampusManager\Resources\AssignedStudentResource\Pages;

use App\Filament\CampusManager\Resources\AssignedStudentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAssignedStudent extends EditRecord
{
    protected static string $resource = AssignedStudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
        ];
    }
}

