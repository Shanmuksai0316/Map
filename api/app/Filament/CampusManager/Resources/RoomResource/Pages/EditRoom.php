<?php

namespace App\Filament\CampusManager\Resources\RoomResource\Pages;

use App\Filament\CampusManager\Resources\RoomResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRoom extends EditRecord
{
    protected static string $resource = RoomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Ensure hostel relationship is loaded for proper form rendering
        if ($this->record && !$this->record->relationLoaded('hostel')) {
            $this->record->load('hostel.tenant');
        }
        
        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $existingBedCount = $this->record?->beds()->count() ?? 0;

        return RoomResource::prepareAutomaticBeds($data, $existingBedCount);
    }
}
