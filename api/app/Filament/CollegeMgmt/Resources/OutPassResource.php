<?php

namespace App\Filament\CollegeMgmt\Resources;

use App\Enums\OutPassStatus;
use App\Filament\CollegeMgmt\Resources\OutPassResource\Pages;
use App\Models\Domain\OutPass\OutPass;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OutPassResource extends Resource
{
    protected static ?string $model = OutPass::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationLabel = 'Out-Passes';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        // Read-only - no form editing
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        $user = Auth::user();
        $tenantId = $user?->tenant_id;

        return $table
            ->modifyQueryUsing(function (Builder $query) use ($tenantId) {
                $query->with(['student.user', 'hostel'])
                    ->where('tenant_id', $tenantId);
                
                return $query->orderBy('requested_at', 'desc');
            })
            ->columns([
                TextColumn::make('unique_id')
                    ->label('Request ID')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('student.user.name')
                    ->label('Student Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('room_number')
                    ->label('Room Number')
                    ->getStateUsing(function (OutPass $record) {
                        $allocation = $record->student->allocation;
                        if (!$allocation || !$allocation->bed) {
                            return '--';
                        }
                        $room = $allocation->bed->room;
                        return $room->block_code . '-' . $room->floor_code . $room->room_no;
                    })
                    ->searchable(),
                TextColumn::make('hostel.name')
                    ->label('Hostel')
                    ->sortable(),
                BadgeColumn::make('reason')
                    ->label('Purpose')
                    ->colors([
                        'primary' => 'normal',
                        'warning' => 'leave',
                        'danger' => 'sick',
                    ])
                    ->formatStateUsing(fn ($state) => ucfirst($state instanceof \BackedEnum ? $state->value : (string) $state)),
                IconColumn::make('overnight')
                    ->label('Overnight')
                    ->boolean(),
                BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        OutPassStatus::PENDING->color() => OutPassStatus::PENDING->value,
                        OutPassStatus::APPROVED->color() => OutPassStatus::APPROVED->value,
                        OutPassStatus::DECLINED->color() => OutPassStatus::DECLINED->value,
                        OutPassStatus::CANCELLED->color() => OutPassStatus::CANCELLED->value,
                        OutPassStatus::EXPIRED->color() => OutPassStatus::EXPIRED->value,
                    ])
                    ->sortable(),
                TextColumn::make('requested_for')
                    ->label('Going Out Date')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('requested_at')
                    ->label('Submitted At')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('decided_at')
                    ->label('Decided At')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('requested_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        OutPassStatus::PENDING->value => OutPassStatus::PENDING->label(),
                        OutPassStatus::APPROVED->value => OutPassStatus::APPROVED->label(),
                        OutPassStatus::DECLINED->value => OutPassStatus::DECLINED->label(),
                        OutPassStatus::CANCELLED->value => OutPassStatus::CANCELLED->label(),
                        OutPassStatus::EXPIRED->value => OutPassStatus::EXPIRED->label(),
                    ]),
                SelectFilter::make('hostel')
                    ->relationship('hostel', 'name')
                    ->label('Hostel'),
                Tables\Filters\Filter::make('date_range')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')
                            ->label('From Date'),
                        \Filament\Forms\Components\DatePicker::make('until')
                            ->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('requested_for', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('requested_for', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->paginated([10, 25, 50, 100]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOutPasses::route('/'),
            'view' => Pages\ViewOutPass::route('/{record}'),
        ];
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();
        return $user && ($user->hasRole('College Management') || $user->hasRole('College Mgmt'));
    }

    public static function canCreate(): bool
    {
        return false; // Read-only
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canView($record): bool
    {
        return static::canAccess();
    }

    public static function canEdit($record): bool
    {
        return false; // Read-only
    }

    public static function canDelete($record): bool
    {
        return false; // Read-only
    }
}
