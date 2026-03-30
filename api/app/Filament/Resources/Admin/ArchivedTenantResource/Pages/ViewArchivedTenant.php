<?php

namespace App\Filament\Resources\Admin\ArchivedTenantResource\Pages;

use App\Filament\Resources\Admin\ArchivedTenantResource;
use Filament\Resources\Pages\ViewRecord;

class ViewArchivedTenant extends ViewRecord
{
    protected static string $resource = ArchivedTenantResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

