<?php

namespace App\Filament\CampusManager\Resources\UnassignedStudentResource\Pages;

use App\Filament\CampusManager\Resources\UnassignedStudentResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUnassignedStudent extends EditRecord
{
    protected static string $resource = UnassignedStudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
        ];
    }
}

