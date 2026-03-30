<?php

namespace App\Filament\Resources\Admin\HostelViewResource\Pages;

use App\Filament\Resources\Admin\HostelViewResource;
use Filament\Resources\Pages\ListRecords;

class ListHostelViews extends ListRecords
{
    protected static string $resource = HostelViewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No create action - read-only
        ];
    }
}
