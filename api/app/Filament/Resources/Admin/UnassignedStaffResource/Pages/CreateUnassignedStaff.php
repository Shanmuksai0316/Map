<?php

namespace App\Filament\Resources\Admin\UnassignedStaffResource\Pages;

use App\Filament\Resources\Admin\UnassignedStaffResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUnassignedStaff extends CreateRecord
{
    protected static string $resource = UnassignedStaffResource::class;
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

