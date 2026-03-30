<?php

namespace App\Filament\CampusManager\Resources\RoomResource\Pages;

use App\Filament\CampusManager\Resources\RoomResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateRoom extends CreateRecord
{
    protected static string $resource = RoomResource::class;

    public function mount(): void
    {
        $tenant = Auth::user()?->tenant;
        
        if ($tenant && $tenant->status === \App\Enums\TenantStatus::ACTIVE) {
            Notification::make()
                ->title('Cannot create new rooms')
                ->body('New rooms cannot be added after tenant activation. Structural changes are locked for data integrity.')
                ->danger()
                ->persistent()
                ->send();
            
            $this->redirect($this->getResource()::getUrl('index'));
        }
        
        parent::mount();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return RoomResource::prepareAutomaticBeds($data);
    }
}
