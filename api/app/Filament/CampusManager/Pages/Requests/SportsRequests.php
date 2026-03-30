<?php

namespace App\Filament\CampusManager\Pages\Requests;

use App\Models\FacilityBooking;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SportsRequests extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $navigationLabel = 'Sports';

    protected static ?string $navigationGroup = 'Requests';

    protected static ?int $navigationSort = 6;

    protected static string $view = 'filament.campus-manager.pages.requests.sports-requests';

    public function getHeading(): string
    {
        return 'Sports Bookings';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                FacilityBooking::query()
                    ->with(['student.user', 'student.roomAllocations.bed.room', 'facility'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('request_id')
                    ->label('Request ID')
                    ->getStateUsing(fn (FacilityBooking $record) => 'SP-' . str_pad($record->id, 4, '0', STR_PAD_LEFT))
                    ->searchable(query: fn (Builder $query, string $search) => 
                        $query->where('id', 'like', '%' . preg_replace('/[^0-9]/', '', $search) . '%')
                    ),

                Tables\Columns\TextColumn::make('student_name')
                    ->label('Student Name')
                    ->getStateUsing(fn (FacilityBooking $record) => $record->student?->user?->name ?? $record->student?->full_name ?? 'Unknown')
                    ->searchable(query: fn (Builder $query, string $search) => 
                        $query->whereHas('student', fn ($q) => $q->where('full_name', 'ilike', "%{$search}%"))
                    ),

                Tables\Columns\TextColumn::make('room')
                    ->label('Room')
                    ->getStateUsing(fn (FacilityBooking $record) => $record->student?->roomAllocations?->first()?->bed?->room?->number ?? '—'),

                Tables\Columns\TextColumn::make('facility.name')
                    ->label('Court Name'),

                Tables\Columns\TextColumn::make('booking_date')
                    ->label('Date')
                    ->getStateUsing(fn (FacilityBooking $record) => $record->start_at?->format('d M Y') ?? '—'),

                Tables\Columns\TextColumn::make('slot')
                    ->label('Slot (Time)')
                    ->getStateUsing(fn (FacilityBooking $record) => 
                        ($record->start_at?->format('h:i A') ?? '—') . ' - ' . ($record->end_at?->format('h:i A') ?? '—')
                    ),
            ])
            ->filters([])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('start_at', 'desc')
            ->emptyStateHeading('No sports bookings')
            ->emptyStateDescription('There are no sports facility bookings at this time.');
    }
}

