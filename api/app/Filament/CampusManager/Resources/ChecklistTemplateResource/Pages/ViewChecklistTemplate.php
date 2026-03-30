<?php

namespace App\Filament\CampusManager\Resources\ChecklistTemplateResource\Pages;

use App\Filament\CampusManager\Resources\ChecklistTemplateResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewChecklistTemplate extends ViewRecord
{
    protected static string $resource = ChecklistTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}

