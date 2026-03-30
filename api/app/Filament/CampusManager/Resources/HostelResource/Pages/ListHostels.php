<?php

namespace App\Filament\CampusManager\Resources\HostelResource\Pages;

use App\Filament\CampusManager\Resources\HostelResource;
use App\Models\Hostel;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Auth;

class ListHostels extends ListRecords
{
    protected static string $resource = HostelResource::class;

    protected function getHeaderActions(): array
    {
        // Remove "New Hostel" button - only one hostel per campus
        return [];
    }

    public function mount(): void
    {
        parent::mount();

        // If there's only one hostel for this tenant, redirect directly to view page
        $tenantId = Auth::user()?->tenant_id;
        
        if ($tenantId) {
            $hostel = Hostel::where('tenant_id', $tenantId)->first();
            
            if ($hostel) {
                // Redirect to view page instead of showing list
                $this->redirect(HostelResource::getUrl('view', ['record' => $hostel]));
            }
        }
    }
}
