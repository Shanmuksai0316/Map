<?php

namespace App\Filament\CampusManager\Resources\OutPassResource\Pages;

use App\Filament\CampusManager\Resources\OutPassResource;
use App\Models\Domain\OutPass\OutPassExport;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Auth;

class ListOutPasses extends ListRecords
{
    protected static string $resource = OutPassResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->requiresConfirmation()
                ->action(function (): void {
                    OutPassExport::create([
                        // tenant_id is handled automatically in tenant database context
                        'requested_by' => Auth::id(),
                        'status' => OutPassExport::STATUS_PENDING,
                    ]);
                })
                ->successNotificationTitle('Export queued'),
        ];
    }
}
