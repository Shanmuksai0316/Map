<?php

namespace App\Filament\CampusManager\Resources\UnassignedStudentResource\Pages;

use App\Filament\CampusManager\Resources\UnassignedStudentResource;
use App\Filament\CampusManager\Resources\StudentResource\Pages\ViewStudent;
use Filament\Actions;

class ViewUnassignedStudent extends ViewStudent
{
    protected static string $resource = UnassignedStudentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

