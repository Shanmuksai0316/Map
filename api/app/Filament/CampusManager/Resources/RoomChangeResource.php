<?php

namespace App\Filament\CampusManager\Resources;

use App\Domain\RoomChanges\Models\RoomChange;
use App\Filament\CampusManager\Resources\RoomChangeResource\Pages;
use App\Models\RoomBed;
use App\Services\RoomChanges\RoomChangeApprovalService;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class RoomChangeResource extends Resource
{
    protected static ?string $model = RoomChange::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationGroup = 'Requests';

    protected static ?string $navigationLabel = 'Room Change Requests';

    protected static ?int $navigationSort = 6;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('unique_id')
                    ->label('Request ID')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('student.user.name')
                    ->label('Student')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('hostel.name')
                    ->label('Hostel')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('preferred_room_number')
                    ->label('Preferred Room')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('sharing_preference')
                    ->label('Sharing')
                    ->badge()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'warning',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('submitted_at')
                    ->label('Submitted')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->label('Status'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (RoomChange $record) => $record->status === 'pending' && (auth()->user()?->can('approve', $record) ?? false))
                    ->form([
                        Forms\Components\Select::make('room_bed_id')
                            ->label('Destination Bed')
                            ->required()
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search) => static::searchBeds($search))
                            ->getOptionLabelUsing(fn ($value) => static::bedLabel($value)),
                        Forms\Components\DateTimePicker::make('effective_from')
                            ->label('Effective From')
                            ->default(now()),
                        Forms\Components\Textarea::make('note')
                            ->label('Notes')
                            ->maxLength(500)
                            ->rows(3),
                    ])
                    ->action(function (RoomChange $record, array $data): void {
                        $bed = RoomBed::findOrFail($data['room_bed_id']);
                        app(RoomChangeApprovalService::class)->approve(
                            $record,
                            $bed,
                            Carbon::parse($data['effective_from'] ?? now()),
                            $data['note'] ?? null
                        );
                    })
                    ->requiresConfirmation(),
                Tables\Actions\Action::make('reject')
                    ->label('Reject')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->visible(fn (RoomChange $record) => $record->status === 'pending' && (auth()->user()?->can('reject', $record) ?? false))
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Reason')
                            ->required()
                            ->maxLength(500)
                            ->rows(3),
                    ])
                    ->action(function (RoomChange $record, array $data): void {
                        app(RoomChangeApprovalService::class)->reject($record, $data['reason']);
                    })
                    ->requiresConfirmation(),
            ])
            ->bulkActions([])
            ->defaultSort('submitted_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRoomChanges::route('/'),
            'view' => Pages\ViewRoomChange::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['student.user', 'hostel']);
    }

    protected static function searchBeds(string $search): array
    {
        return RoomBed::query()
            ->where('status', 'available')
            ->where(function ($query) use ($search): void {
                $query->where('code', 'like', '%' . $search . '%')
                    ->orWhereHas('room', fn ($roomQuery) => $roomQuery->where('number', 'like', '%' . $search . '%'));
            })
            ->with(['room', 'hostel'])
            ->limit(50)
            ->get()
            ->mapWithKeys(fn (RoomBed $bed) => [
                $bed->id => static::formatBedLabel($bed),
            ])
            ->toArray();
    }

    protected static function bedLabel(int|string $id): ?string
    {
        $bed = RoomBed::with(['room', 'hostel'])->find($id);

        return $bed ? static::formatBedLabel($bed) : null;
    }

    protected static function formatBedLabel(RoomBed $bed): string
    {
        return sprintf('%s · Room %s · %s', $bed->code, $bed->room?->number, $bed->hostel?->name);
    }
}
