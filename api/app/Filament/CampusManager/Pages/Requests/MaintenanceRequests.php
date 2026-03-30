<?php

namespace App\Filament\CampusManager\Pages\Requests;

use App\Domain\Tickets\Models\Ticket;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class MaintenanceRequests extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationLabel = 'Repair & Maintenance';

    protected static ?string $navigationGroup = 'Requests';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.campus-manager.pages.requests.maintenance-requests';

    public function getHeading(): string
    {
        return 'Repair & Maintenance Requests';
    }

    public function table(Table $table): Table
    {
        try {
            return $table
                ->query(
                    Ticket::query()
                        ->whereIn('category', ['maintenance', 'repair_maintenance'])
                        ->with(['reporterStudent.user', 'reporterStudent.roomAllocations.roomBed.room'])
                )
            ->columns([
                Tables\Columns\TextColumn::make('request_id')
                    ->label('Request ID')
                    ->getStateUsing(fn (Ticket $record) => 'RM-' . str_pad($record->id, 4, '0', STR_PAD_LEFT))
                    ->searchable(query: fn (Builder $query, string $search) => 
                        $query->where('id', 'like', '%' . preg_replace('/[^0-9]/', '', $search) . '%')
                    ),

                Tables\Columns\TextColumn::make('student_room')
                    ->label('Student Name - Room')
                    ->getStateUsing(function (Ticket $record) {
                        $studentName = $record->reporter_name ?? 'Unknown';
                        $room = $record->reporterStudent?->roomAllocations?->first()?->roomBed?->room?->number ?? '—';
                        return "{$studentName} - {$room}";
                    })
                    ->searchable(query: fn (Builder $query, string $search) => 
                        $query->where('reporter_name', 'ilike', "%{$search}%")
                    ),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match($state) {
                        'open' => 'Pending',
                        'in_progress' => 'In Progress',
                        'resolved', 'closed' => 'Resolved',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state) => match($state) {
                        'open' => 'warning',
                        'in_progress' => 'info',
                        'resolved', 'closed' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('delayed')
                    ->label('SLA')
                    ->badge()
                    ->getStateUsing(fn (Ticket $record) => $record->isDelayed() ? 'Delayed' : 'On time')
                    ->color(fn (Ticket $record) => $record->isDelayed() ? 'danger' : 'success')
                    ->formatStateUsing(fn (Ticket $record) => $record->isDelayed() ? 'Delayed' : 'On time'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'open' => 'Pending',
                        'in_progress' => 'In Progress',
                        'resolved' => 'Resolved',
                    ])
                    ->placeholder('All'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Repair & Maintenance')
                    ->modalContent(fn (Ticket $record) => view('filament.campus-manager.pages.requests.request-modal', [
                        'title' => 'Repair & Maintenance',
                        'requestId' => 'RM-' . str_pad($record->id, 4, '0', STR_PAD_LEFT),
                        'studentName' => $record->reporter_name ?? 'Unknown',
                        'roomNumber' => $record->reporterStudent?->roomAllocations?->first()?->roomBed?->room?->number ?? '—',
                        'submittedAt' => $record->created_at->format('d M Y, h:i A'),
                        'status' => match($record->status) {
                            'open' => 'Pending',
                            'in_progress' => 'In Progress',
                            'resolved', 'closed' => 'Resolved',
                            default => ucfirst($record->status),
                        },
                        'description' => $record->description ?? '—',
                    ]))
                    ->modalWidth('md')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No repair & maintenance requests')
            ->emptyStateDescription('There are no repair & maintenance requests at this time.');
        } catch (\Exception $e) {
            \Log::error('MaintenanceRequests: Error in table()', [
                'error' => $e->getMessage(),
                'trace' => substr($e->getTraceAsString(), 0, 500),
            ]);
            
            // Return a safe table query that won't crash
            return $table
                ->query(Ticket::query()->whereRaw('1 = 0')) // Empty query
                ->columns([])
                ->emptyStateHeading('Error loading requests')
                ->emptyStateDescription('Please refresh the page or contact support if the issue persists.');
        }
    }
}

