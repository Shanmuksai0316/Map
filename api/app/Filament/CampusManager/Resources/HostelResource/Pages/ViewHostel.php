<?php

namespace App\Filament\CampusManager\Resources\HostelResource\Pages;

use App\Filament\CampusManager\Resources\HostelResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewHostel extends ViewRecord
{
    protected static string $resource = HostelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}




