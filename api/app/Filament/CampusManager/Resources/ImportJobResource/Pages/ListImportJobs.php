<?php

namespace App\Filament\CampusManager\Resources\ImportJobResource\Pages;

use App\Filament\CampusManager\Resources\ImportJobResource;
use App\Filament\CampusManager\Resources\ImportJobResource\Pages\StartImport;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListImportJobs extends ListRecords
{
    protected static string $resource = ImportJobResource::class;

    /**
     * Show an Upload CSV button that goes to the Start Import page.
     */
    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('uploadCsv')
                ->label('Upload CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('primary')
                ->url(StartImport::getUrl()),
        ];
    }
}
