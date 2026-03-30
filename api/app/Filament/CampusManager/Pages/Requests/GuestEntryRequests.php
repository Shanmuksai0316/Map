<?php

namespace App\Filament\CampusManager\Pages\Requests;

use App\Domain\Visitors\Models\GuestVisit;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GuestEntryRequests extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Guest Entry';

    protected static ?string $navigationGroup = 'Requests';

    protected static ?int $navigationSort = 5;

    protected static string $view = 'filament.campus-manager.pages.requests.guest-entry-requests';

    public function getHeading(): string
    {
        return 'Guest Entry Requests';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                GuestVisit::query()
                    ->with(['student.user', 'student.roomAllocations.bed.room'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('request_id')
                    ->label('Request ID')
                    ->getStateUsing(fn (GuestVisit $record) => 'GE-' . str_pad($record->id, 4, '0', STR_PAD_LEFT))
                    ->searchable(query: fn (Builder $query, string $search) => 
                        $query->where('id', 'like', '%' . preg_replace('/[^0-9]/', '', $search) . '%')
                    ),

                Tables\Columns\TextColumn::make('student_name')
                    ->label('Student Name')
                    ->getStateUsing(fn (GuestVisit $record) => $record->student?->user?->name ?? $record->student?->full_name ?? 'Unknown')
                    ->searchable(query: fn (Builder $query, string $search) => 
                        $query->whereHas('student', fn ($q) => $q->where('full_name', 'ilike', "%{$search}%"))
                    ),

                Tables\Columns\TextColumn::make('room')
                    ->label('Room')
                    ->getStateUsing(fn (GuestVisit $record) => $record->student?->roomAllocations?->first()?->bed?->room?->number ?? '—'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match($state) {
                        'pending', 'pre_registered' => 'Pending',
                        'allowed', 'approved' => 'Approved',
                        'denied' => 'Denied',
                        'completed' => 'Completed',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state) => match($state) {
                        'pending', 'pre_registered' => 'warning',
                        'allowed', 'approved' => 'success',
                        'denied' => 'danger',
                        'completed' => 'gray',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('name')
                    ->label('Guest Name'),

                Tables\Columns\TextColumn::make('relation')
                    ->label('Guest Relation')
                    ->default('—'),

                Tables\Columns\TextColumn::make('visit_date')
                    ->label('Guest Arrival Date')
                    ->date('d M Y'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'allowed' => 'Approved',
                        'denied' => 'Denied',
                    ])
                    ->placeholder('All'),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Guest Entry')
                    ->modalContent(fn (GuestVisit $record) => view('filament.campus-manager.pages.requests.guest-entry-modal', [
                        'requestId' => 'GE-' . str_pad($record->id, 4, '0', STR_PAD_LEFT),
                        'studentName' => $record->student?->user?->name ?? $record->student?->full_name ?? 'Unknown',
                        'roomNumber' => $record->student?->roomAllocations?->first()?->bed?->room?->number ?? '—',
                        'guestName' => $record->name ?? '—',
                        'guestRelation' => $record->relation ?? '—',
                        'guestIdProof' => $record->id_proof_type ? "{$record->id_proof_type}: {$record->id_proof_number}" : '—',
                        'primaryContact' => $record->phone ?? '—',
                        'guestArrivalDate' => $record->visit_date?->format('d M Y') ?? '—',
                        'submittedAt' => $record->created_at->format('d M Y, h:i A'),
                        'status' => match($record->status) {
                            'pending', 'pre_registered' => 'Pending',
                            'allowed', 'approved' => 'Approved',
                            'denied' => 'Denied',
                            'completed' => 'Completed',
                            default => ucfirst($record->status),
                        },
                        'description' => $record->description ?? '—',
                    ]))
                    ->modalWidth('lg')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No guest entry requests')
            ->emptyStateDescription('There are no guest entry requests at this time.');
    }
}

