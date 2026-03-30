<?php

namespace App\Filament\CampusManager\Resources\ChecklistTemplateResource\Pages;

use App\Filament\CampusManager\Resources\ChecklistTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditChecklistTemplate extends EditRecord
{
    protected static string $resource = ChecklistTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}

