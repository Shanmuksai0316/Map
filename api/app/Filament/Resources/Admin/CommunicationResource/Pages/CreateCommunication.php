<?php

namespace App\Filament\Resources\Admin\CommunicationResource\Pages;

use App\Filament\Resources\Admin\CommunicationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCommunication extends CreateRecord
{
    protected static string $resource = CommunicationResource::class;
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

