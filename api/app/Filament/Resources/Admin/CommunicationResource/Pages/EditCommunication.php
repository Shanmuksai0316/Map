<?php

namespace App\Filament\Resources\Admin\CommunicationResource\Pages;

use App\Filament\Resources\Admin\CommunicationResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCommunication extends EditRecord
{
    protected static string $resource = CommunicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

