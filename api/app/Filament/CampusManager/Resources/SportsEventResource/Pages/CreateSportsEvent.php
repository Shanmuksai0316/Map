<?php

namespace App\Filament\CampusManager\Resources\SportsEventResource\Pages;

use App\Filament\CampusManager\Resources\SportsEventResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSportsEvent extends CreateRecord
{
    protected static string $resource = SportsEventResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Sports event created successfully';
    }
}

