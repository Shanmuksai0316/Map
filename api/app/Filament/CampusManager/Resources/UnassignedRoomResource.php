<?php

namespace App\Filament\CampusManager\Resources;

use App\Filament\CampusManager\Resources\UnassignedRoomResource\Pages;
use App\Models\Room;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class UnassignedRoomResource extends Resource
{
    protected static ?string $model = Room::class;

    protected static ?string $navigationIcon = 'heroicon-o-home-modern';

    protected static ?string $navigationGroup = 'Rooms & Allocation';

    protected static ?string $navigationLabel = 'Unassigned Rooms';

    protected static ?string $modelLabel = 'Unassigned Room';

    protected static ?string $pluralModelLabel = 'Unassigned Rooms';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'rooms/unassigned';

    public static function form(Form $form): Form
    {
        return RoomResource::form($form);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $tenantId = static::getTenantId();
                if ($tenantId) {
                    $query->where('tenant_id', $tenantId);
                }
                // Apply hostel switcher scope
                $activeHostelId = session('active_hostel_id');
                if ($activeHostelId) {
                    $query->where('hostel_id', $activeHostelId);
                }
                // Only rooms with no occupied beds (all beds available or empty)
                return $query->where(function ($q) {
                    $q->whereDoesntHave('beds', function ($bedQ) {
                        $bedQ->where('status', 'occupied');
                    });
                });
            })
            ->columns([
                Tables\Columns\TextColumn::make('number')
                    ->label('Room No')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('hostel.name')
                    ->label('Hostel')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('floor_code')
                    ->label('Floor')
                    ->sortable(),

                Tables\Columns\TextColumn::make('room_type')
                    ->label('Type')
                    ->formatStateUsing(fn (?string $state) => $state ? ucfirst(strtolower($state)) : '—')
                    ->badge(),

                Tables\Columns\TextColumn::make('capacity')
                    ->label('Capacity')
                    ->getStateUsing(fn (Room $record) => $record->capacity ?? $record->beds()->count())
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('hostel_id')
                    ->label('All Rooms')
                    ->options(fn () => \App\Models\Hostel::pluck('name', 'id')->toArray())
                    ->searchable()
                    ->placeholder('All Rooms'),

                SelectFilter::make('room_type')
                    ->label('Type')
                    ->options([
                        'Single' => 'Single',
                        'Double' => 'Double',
                        'Triple' => 'Triple',
                        'Quad' => 'Four-bed',
                    ])
                    ->placeholder('All Types'),
            ])
            ->defaultSort('number')
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUnassignedRooms::route('/'),
        ];
    }

    protected static function getTenantId(): ?string
    {
        if (Auth::check() && Auth::user()->tenant_id) {
            return Auth::user()->tenant_id;
        }

        if (function_exists('tenant') && tenant()) {
            return tenant()->id;
        }

        return null;
    }

    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        return Auth::check() && $user && $user->hasAnyRole(['Campus Manager', 'Super Admin', 'Rector']);
    }
}

