<?php

namespace App\Filament\Resources\Admin\ArchivedTenantResource\Pages;

use App\Filament\Resources\Admin\ArchivedTenantResource;
use Filament\Resources\Pages\ListRecords;

class ListArchivedTenants extends ListRecords
{
    protected static string $resource = ArchivedTenantResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
    
    public function getTableEmptyStateHeading(): ?string
    {
        return 'No archived tenants';
    }
    
    public function getTableEmptyStateDescription(): ?string
    {
        return 'Archived tenants will appear here once they are archived from the All Tenants view.';
    }
}

