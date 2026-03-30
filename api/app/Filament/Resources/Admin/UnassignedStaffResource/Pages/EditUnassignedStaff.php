<?php

namespace App\Filament\Resources\Admin\UnassignedStaffResource\Pages;

use App\Filament\Resources\Admin\UnassignedStaffResource;
use Filament\Resources\Pages\EditRecord;

class EditUnassignedStaff extends EditRecord
{
    protected static string $resource = UnassignedStaffResource::class;
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

