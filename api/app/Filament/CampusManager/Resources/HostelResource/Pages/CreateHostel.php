<?php

namespace App\Filament\CampusManager\Resources\HostelResource\Pages;

use App\Filament\CampusManager\Resources\HostelResource;
use App\Models\Tenant;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateHostel extends CreateRecord
{
    protected static string $resource = HostelResource::class;

    public function mount(): void
    {
        $tenant = Auth::user()?->tenant;
        
        if ($tenant && $tenant->status === \App\Enums\TenantStatus::ACTIVE) {
            Notification::make()
                ->title('Cannot create new hostels')
                ->body('New hostels cannot be added after tenant activation. Structural changes are locked for data integrity.')
                ->danger()
                ->persistent()
                ->send();
            
            $this->redirect($this->getResource()::getUrl('index'));
        }
        
        parent::mount();
    }

    // No afterCreate hook needed – branding (logo) is configured
    // at the Tenant level from the Admin panel.
}
