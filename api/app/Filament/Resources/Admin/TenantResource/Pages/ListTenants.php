<?php

namespace App\Filament\Resources\Admin\TenantResource\Pages;

use App\Filament\Resources\Admin\TenantResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTenants extends ListRecords
{
    protected static string $resource = TenantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Removed: Actions\CreateAction::make()
            // All tenants must be created via Onboarding Wizard
            Actions\Action::make('onboard_new_tenant')
                ->label('New Tenant Onboarding')
                ->icon('heroicon-o-rocket-launch')
                ->url(route('filament.admin.pages.tenant-onboarding-wizard'))
                ->color('primary')
                ->tooltip('Create a new tenant with complete setup: database, campus, hostel, and staff'),
        ];
    }
    
    public function getTableEmptyStateHeading(): ?string
    {
        return 'No tenants yet';
    }
    
    public function getTableEmptyStateDescription(): ?string
    {
        return 'Start by onboarding your first tenant using the Onboarding Wizard.';
    }
    
    public function getTableEmptyStateActions(): array
    {
        return [
            Actions\Action::make('onboard')
                ->label('Start Onboarding')
                ->icon('heroicon-o-rocket-launch')
                ->url(route('filament.admin.pages.tenant-onboarding-wizard'))
                ->color('primary'),
        ];
    }
}
