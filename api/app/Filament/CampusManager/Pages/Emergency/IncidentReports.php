<?php

namespace App\Filament\CampusManager\Pages\Emergency;

use App\Models\Incident;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class IncidentReports extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationLabel = 'Incidents';

    protected static ?string $navigationGroup = 'Emergency';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.campus-manager.pages.emergency.incident-reports';

    public function getHeading(): string
    {
        return 'Incident Reports';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Incident::query()
                    ->whereIn('type', Incident::nonMedicalTypes())
                    ->with(['student.user', 'student.roomAllocations.bed.room'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('student_name')
                    ->label('Student Name')
                    ->getStateUsing(fn (Incident $record) => $record->student?->user?->name ?? $record->student?->full_name ?? 'Unknown')
                    ->searchable(query: fn (Builder $query, string $search) => 
                        $query->whereHas('student', fn ($q) => $q->where('full_name', 'ilike', "%{$search}%"))
                    ),

                Tables\Columns\TextColumn::make('room_number')
                    ->label('Room Number')
                    ->getStateUsing(fn (Incident $record) => $record->student?->roomAllocations?->first()?->bed?->room?->number ?? '—'),

                Tables\Columns\TextColumn::make('opened_at')
                    ->label('Submitted Date & Time')
                    ->dateTime('d M Y, h:i A'),
            ])
            ->recordClasses(function (Incident $record) {
                // Apply red blinking animation for unacknowledged incidents
                if (!$record->isAcknowledged()) {
                    return 'animate-pulse-red bg-red-50 dark:bg-red-900/20';
                }
                return '';
            })
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Incident Report')
                    ->modalContent(fn (Incident $record) => view('filament.campus-manager.pages.emergency.incident-modal', [
                        'studentName' => $record->student?->user?->name ?? $record->student?->full_name ?? 'Unknown',
                        'roomNumber' => $record->student?->roomAllocations?->first()?->bed?->room?->number ?? '—',
                        'incidentType' => $record->type,
                        'submittedAt' => $record->opened_at?->format('d M Y, h:i A') ?? $record->created_at->format('d M Y, h:i A'),
                        'note' => $record->note ?? '—',
                        'isAcknowledged' => $record->isAcknowledged(),
                    ]))
                    ->modalWidth('md')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->extraModalFooterActions([
                        Tables\Actions\Action::make('acknowledge')
                            ->label('Acknowledge')
                            ->color('success')
                            ->icon('heroicon-o-check-circle')
                            ->action(function (Incident $record) {
                                $record->acknowledge(auth()->id());
                                Notification::make()
                                    ->success()
                                    ->title('Incident acknowledged')
                                    ->send();
                            })
                            ->visible(fn (Incident $record) => !$record->isAcknowledged()),
                    ]),
            ])
            ->bulkActions([])
            ->defaultSort('opened_at', 'desc')
            ->emptyStateHeading('No incident reports')
            ->emptyStateDescription('There are no incident reports at this time.')
            ->poll('10s'); // Auto-refresh every 10 seconds
    }
}

