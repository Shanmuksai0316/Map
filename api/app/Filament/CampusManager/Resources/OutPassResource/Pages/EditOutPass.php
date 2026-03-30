<?php

namespace App\Filament\CampusManager\Resources\OutPassResource\Pages;

use App\Filament\CampusManager\Resources\OutPassResource;
use Filament\Resources\Pages\EditRecord;

class EditOutPass extends EditRecord
{
    protected static string $resource = OutPassResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
