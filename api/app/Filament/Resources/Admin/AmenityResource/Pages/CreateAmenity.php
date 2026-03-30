<?php

namespace App\Filament\Resources\Admin\AmenityResource\Pages;

use App\Filament\Resources\Admin\AmenityResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAmenity extends CreateRecord
{
    protected static string $resource = AmenityResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

