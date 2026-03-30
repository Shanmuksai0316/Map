<?php

namespace App\Filament\CampusManager\Resources\LaundryRequestResource\Pages;

use App\Filament\CampusManager\Resources\LaundryRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateLaundryRequest extends CreateRecord
{
    protected static string $resource = LaundryRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set default timestamps
        $data['requested_at'] = $data['requested_at'] ?? now();
        
        // Set default status if not provided
        if (!isset($data['status'])) {
            $data['status'] = 'requested';
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Laundry request created successfully';
    }
}

