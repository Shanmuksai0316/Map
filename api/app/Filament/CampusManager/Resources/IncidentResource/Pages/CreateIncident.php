<?php

namespace App\Filament\CampusManager\Resources\IncidentResource\Pages;

use App\Filament\CampusManager\Resources\IncidentResource;
use Filament\Resources\Pages\CreateRecord;

class CreateIncident extends CreateRecord
{
    protected static string $resource = IncidentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // tenant_id is handled automatically in tenant database context
        $data['opened_by'] = auth()->id();
        $data['opened_at'] = now();
        $data['status'] = 'Open';

        return $data;
    }
}

