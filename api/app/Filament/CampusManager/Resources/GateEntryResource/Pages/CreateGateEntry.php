<?php

namespace App\Filament\CampusManager\Resources\GateEntryResource\Pages;

use App\Filament\CampusManager\Resources\GateEntryResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateGateEntry extends CreateRecord
{
    protected static string $resource = GateEntryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set tenant_id from authenticated user or tenancy context
        if (!isset($data['tenant_id'])) {
            $data['tenant_id'] = Auth::user()?->tenant_id ?? tenancy()?->tenant?->id;
        }

        $data['guard_id'] = Auth::id();
        $data['source'] = $data['source'] ?? 'web';

        return $data;
    }
}
