<?php

namespace App\Filament\CampusManager\Resources\ChecklistTemplateResource\Pages;

use App\Filament\CampusManager\Resources\ChecklistTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateChecklistTemplate extends CreateRecord
{
    protected static string $resource = ChecklistTemplateResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = auth()->user()->tenant_id;
        $data['created_by_user_id'] = auth()->id();

        return $data;
    }
}

