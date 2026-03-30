<?php

namespace App\Filament\CampusManager\Resources\HostelResource\Pages;

use App\Filament\CampusManager\Resources\HostelResource;
use App\Models\Tenant;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditHostel extends EditRecord
{
    protected static string $resource = HostelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    // Logo / branding is managed at the Tenant level from the Admin panel.
    // No additional hooks are needed here – this page only edits hostel data.
}
