<?php

namespace App\Filament\CampusManager\Resources\LaundryRequestResource\Pages;

use App\Filament\CampusManager\Resources\LaundryRequestResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditLaundryRequest extends EditRecord
{
    protected static string $resource = LaundryRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Laundry request updated successfully';
    }
}

