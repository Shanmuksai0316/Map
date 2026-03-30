<?php

namespace App\Filament\CampusManager\Pages\Requests;

use App\Models\LaundryRequest;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LaundryRequests extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationLabel = 'Laundry';

    protected static ?string $navigationGroup = 'Requests';

    protected static ?int $navigationSort = 7;

    protected static string $view = 'filament.campus-manager.pages.requests.laundry-requests';

    protected static ?string $slug = null; // Remove slug to prevent route generation

    /**
     * Prevent this page from appearing in navigation.
     * The page route might not be properly registered, causing route generation errors.
     * Since we have LaundryRequestResource for laundry management, this standalone page is redundant.
     */
    public static function shouldRegisterNavigation(): bool
    {
        // Always return false - this page should not appear in navigation
        // Use LaundryRequestResource instead, which has proper route registration
        return false;
    }

    /**
     * Override route name generation to prevent route registration errors.
     * Since this page should not be accessible, we prevent route generation entirely.
     */
    public static function getRouteName(?string $panel = null): string
    {
        // Return empty string to prevent route generation - page should not be accessible
        // This prevents Filament from trying to register routes for this page
        return '';
    }

    /**
     * Override mount to prevent route generation errors when this page is discovered but not registered.
     */
    public function mount(): void
    {
        // Prevent mounting this page - it should not be accessible
        // Redirect to LaundryRequestResource instead
        abort(404, 'This page is not available. Use Laundry Requests resource instead.');
    }

    public function getHeading(): string
    {
        return 'Laundry Requests';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                LaundryRequest::query()
                    ->with(['student.user', 'student.roomAllocations.bed.room'])
            )
            ->columns([
                Tables\Columns\TextColumn::make('request_id')
                    ->label('Request ID')
                    ->getStateUsing(fn (LaundryRequest $record) => 'LR-' . str_pad($record->id, 4, '0', STR_PAD_LEFT))
                    ->searchable(query: fn (Builder $query, string $search) => 
                        $query->where('id', 'like', '%' . preg_replace('/[^0-9]/', '', $search) . '%')
                    ),

                Tables\Columns\TextColumn::make('student_name')
                    ->label('Student Name')
                    ->getStateUsing(fn (LaundryRequest $record) => $record->student?->user?->name ?? $record->student?->full_name ?? 'Unknown')
                    ->searchable(query: fn (Builder $query, string $search) => 
                        $query->whereHas('student', fn ($q) => $q->where('full_name', 'ilike', "%{$search}%"))
                    ),

                Tables\Columns\TextColumn::make('room')
                    ->label('Room')
                    ->getStateUsing(fn (LaundryRequest $record) => $record->student?->roomAllocations?->first()?->bed?->room?->number ?? '—'),

                Tables\Columns\TextColumn::make('total_clothes')
                    ->label('Total Clothes')
                    ->getStateUsing(fn (LaundryRequest $record) => $record->total_clothes ?? $record->bag_count ?? '—'),

                Tables\Columns\TextColumn::make('weight_kg')
                    ->label('Total Weight')
                    ->getStateUsing(fn (LaundryRequest $record) => $record->weight_kg ? "{$record->weight_kg} kg" : '—'),

                Tables\Columns\TextColumn::make('requested_at')
                    ->label('Submitted Date & Time')
                    ->dateTime('d M Y, h:i A'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state?->label() ?? ucfirst((string) $state))
                    ->color(fn ($state) => match($state?->value ?? (string) $state) {
                        'scheduled', 'pending' => 'warning',
                        'collected', 'processing' => 'info',
                        'ready' => 'primary',
                        'delivered', 'completed' => 'success',
                        default => 'gray',
                    }),
            ])
            ->filters([])
            ->actions([])
            ->bulkActions([])
            ->defaultSort('requested_at', 'desc')
            ->emptyStateHeading('No laundry requests')
            ->emptyStateDescription('There are no laundry requests at this time.');
    }
}

