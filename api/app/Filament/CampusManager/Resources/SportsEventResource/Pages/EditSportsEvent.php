<?php

namespace App\Filament\CampusManager\Resources\SportsEventResource\Pages;

use App\Filament\CampusManager\Resources\SportsEventResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSportsEvent extends EditRecord
{
    protected static string $resource = SportsEventResource::class;

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
        return 'Sports event updated successfully';
    }
}

