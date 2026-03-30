<?php

namespace App\Filament\CampusManager\Resources;

use App\Filament\CampusManager\Resources\RoomResource\Pages;
use App\Models\Campus;
use App\Models\Hostel;
use App\Models\Room;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class RoomResource extends Resource
{
    protected static ?string $model = Room::class;

    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static ?string $navigationGroup = 'Rooms & Allocation';

    protected static ?string $navigationLabel = 'Room Overview';

    protected static ?string $modelLabel = 'Room';

    protected static ?string $pluralModelLabel = 'Rooms';

    protected static ?int $navigationSort = 1;

    protected static ?string $tenantOwnershipRelationshipName = 'tenant';

    protected static array $roomTypeCapacityMap = [
        'single' => 1,
        'double' => 2,
        'suite' => 1,
    ];

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Location')
                    ->columns(2)
                    ->schema([
                        Select::make('campus_id')
                            ->label('Campus')
                            ->options(fn () => static::campusOptions())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live(),
                        Select::make('hostel_id')
                            ->label('Hostel')
                            ->options(fn (callable $get) => static::hostelOptions($get('campus_id')))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live(),
                    ]),
                Section::make('Room Details')
                    ->columns(3)
                    ->schema([
                        TextInput::make('block_code')
                            ->maxLength(16)
                            ->disabled(fn ($record) => $record && $record->hostel && $record->hostel->tenant && $record->hostel->tenant->status === \App\Enums\TenantStatus::ACTIVE)
                            ->dehydrated(fn ($record) => !$record || !$record->hostel || !$record->hostel->tenant || $record->hostel->tenant->status !== \App\Enums\TenantStatus::ACTIVE)
                            ->helperText(fn ($record) => $record && $record->hostel && $record->hostel->tenant && $record->hostel->tenant->status === \App\Enums\TenantStatus::ACTIVE 
                                ? 'Structural fields cannot be changed after activation' 
                                : null),
                        TextInput::make('floor_code')
                            ->maxLength(16)
                            ->disabled(fn ($record) => $record && $record->hostel && $record->hostel->tenant && $record->hostel->tenant->status === \App\Enums\TenantStatus::ACTIVE)
                            ->dehydrated(fn ($record) => !$record || !$record->hostel || !$record->hostel->tenant || $record->hostel->tenant->status !== \App\Enums\TenantStatus::ACTIVE)
                            ->helperText(fn ($record) => $record && $record->hostel && $record->hostel->tenant && $record->hostel->tenant->status === \App\Enums\TenantStatus::ACTIVE 
                                ? 'Structural fields cannot be changed after activation' 
                                : null),
                        TextInput::make('number')
                            ->label('Room Number')
                            ->required()
                            ->maxLength(16)
                            ->helperText('Room number is editable by Campus Manager. Must be unique within the same hostel.')
                            ->rule(function (callable $get, ?Room $record) {
                                // In tenant database context, we don't need tenant_id filtering for uniqueness
                                return Rule::unique('rooms', 'number')
                                    ->ignore($record?->id)
                                    ->where('hostel_id', $get('hostel_id'));
                            }),
                        Select::make('room_type')
                            ->label('Room Type')
                            ->options(static::roomTypeOptions())
                            ->required()
                            ->default('Single')
                            ->native(false)
                            ->helperText('Controls the default bed configuration (Single = 1 bed, Double = 2 beds, Suite = 1 bed).')
                            ->disabled(fn ($record) => $record && $record->hostel && $record->hostel->tenant && $record->hostel->tenant->status === \App\Enums\TenantStatus::ACTIVE),
                        TextInput::make('capacity')
                            ->label('Bed Capacity')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(16)
                            ->default(4)
                            ->required()
                            ->disabled(fn ($record) => $record && $record->hostel && $record->hostel->tenant && $record->hostel->tenant->status === \App\Enums\TenantStatus::ACTIVE)
                            ->dehydrated(fn ($record) => !$record || !$record->hostel || !$record->hostel->tenant || $record->hostel->tenant->status !== \App\Enums\TenantStatus::ACTIVE)
                            ->helperText(fn ($record) => $record && $record->hostel && $record->hostel->tenant && $record->hostel->tenant->status === \App\Enums\TenantStatus::ACTIVE 
                                ? 'Capacity cannot be changed after activation' 
                                : null),
                        Toggle::make('is_active')
                            ->default(true)
                            ->inline(false)
                            ->columnSpan(1),
                    ]),
                Section::make('Beds')
                    ->description('Maintain bed inventory for allocations.')
                    ->schema([
                        Repeater::make('beds')
                            ->relationship()
                            ->minItems(0)
                            ->columns(3)
                            ->schema([
                                TextInput::make('code')
                                    ->label('Code')
                                    ->required()
                                    ->maxLength(16),
                                Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        'available' => 'Available',
                                        'occupied' => 'Occupied',
                                        'blocked' => 'Blocked',
                                        'maintenance' => 'Maintenance',
                                    ])
                                    ->default('available')
                                    ->required()
                                    ->columnSpan(2),
                            ])
                            ->helperText('Set blocked or occupied to reflect maintenance or allocations.')
                            ->collapsible(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // Room No
                Tables\Columns\TextColumn::make('number')
                    ->label('Room No')
                    ->sortable()
                    ->searchable(),

                // Hostel
                Tables\Columns\TextColumn::make('hostel.name')
                    ->label('Hostel')
                    ->sortable()
                    ->searchable(),

                // Floor
                Tables\Columns\TextColumn::make('floor_code')
                    ->label('Floor')
                    ->sortable()
                    ->searchable(),

                // Type (Single, Double, Triple, Quad)
                Tables\Columns\TextColumn::make('room_type')
                    ->label('Type')
                    ->formatStateUsing(fn (?string $state) => $state ? ucfirst(strtolower($state)) : '—')
                    ->badge()
                    ->sortable(),

                // Occupancy (e.g., "2/4")
                Tables\Columns\TextColumn::make('occupancy')
                    ->label('Occupancy')
                    ->getStateUsing(function (Room $record) {
                        $totalBeds = $record->beds()->count();
                        $occupiedBeds = $record->beds()->where('status', 'occupied')->count();
                        return "{$occupiedBeds}/{$totalBeds}";
                    })
                    ->badge()
                    ->color(function (Room $record) {
                        $totalBeds = $record->beds()->count();
                        $occupiedBeds = $record->beds()->where('status', 'occupied')->count();
                        if ($totalBeds === 0) return 'gray';
                        $ratio = $occupiedBeds / $totalBeds;
                        if ($ratio >= 1) return 'danger';
                        if ($ratio >= 0.5) return 'warning';
                        return 'success';
                    }),

                // Status (Active/Inactive)
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->getStateUsing(fn (Room $record) => $record->is_active ? 'Active' : 'Inactive')
                    ->badge()
                    ->color(fn (Room $record) => $record->is_active ? 'success' : 'gray'),

                // Occupants (count with click to view)
                Tables\Columns\TextColumn::make('occupants')
                    ->label('Occupants')
                    ->getStateUsing(function (Room $record) {
                        $occupiedBeds = $record->beds()->where('status', 'occupied')->count();
                        return $occupiedBeds > 0 ? "{$occupiedBeds} student(s)" : '—';
                    }),
            ])
            ->filters([
                SelectFilter::make('hostel_id')
                    ->label('Hostel')
                    ->options(fn () => static::hostelOptions())
                    ->searchable()
                    ->placeholder('All Hostels'),

                SelectFilter::make('floor_code')
                    ->label('Floor')
                    ->options(fn () => static::floorCodeOptions())
                    ->searchable()
                    ->placeholder('All Floors'),

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
        return [
            // relation managers (e.g., allocations history) can be added later
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRooms::route('/'),
            'create' => Pages\CreateRoom::route('/create'),
            'edit' => Pages\EditRoom::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->when(static::getTenantId(), fn (Builder $query, $tenantId) => $query->where('tenant_id', $tenantId))
            ->when(session('active_hostel_id'), fn (Builder $query, $hostelId) => $query->where('hostel_id', $hostelId))
            ->with(['hostel.tenant'])
            ->withCount('beds');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->count();
    }

    protected static function campusOptions(): array
    {
        return Campus::query()
            ->when(static::getTenantId(), fn (Builder $query, $tenantId) => $query->where('tenant_id', $tenantId))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    protected static function hostelOptions(?int $campusId = null): array
    {
        return Hostel::query()
            ->when($campusId, fn (Builder $query) => $query->where('campus_id', $campusId))
            ->when(static::getTenantId(), fn (Builder $query, $tenantId) => $query->where('tenant_id', $tenantId))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    /** Distinct floor_code values for Room Overview filter (block not used in hostel config). */
    protected static function floorCodeOptions(): array
    {
        return Room::query()
            ->when(static::getTenantId(), fn (Builder $query, $tenantId) => $query->where('tenant_id', $tenantId))
            ->whereNotNull('floor_code')
            ->where('floor_code', '!=', '')
            ->distinct()
            ->orderBy('floor_code')
            ->pluck('floor_code', 'floor_code')
            ->toArray();
    }

    public static function prepareAutomaticBeds(array $data, int $existingBedCount = 0): array
    {
        $normalizedType = static::normalizeRoomType($data['room_type'] ?? null);

        if ($normalizedType) {
            $data['room_type'] = $normalizedType;
        }

        $expectedBeds = static::expectedBedsForRoomType($normalizedType);

        if ($expectedBeds === null) {
            return $data;
        }

        $data['capacity'] = $expectedBeds;

        $incomingBeds = $data['beds'] ?? [];

        if ($existingBedCount > 0 || ! empty($incomingBeds)) {
            return $data;
        }

        $data['beds'] = static::generateBedsPayload($expectedBeds, $data['number'] ?? null);

        return $data;
    }

    protected static function roomTypeOptions(): array
    {
        return [
            'Single' => 'Single',
            'Double' => 'Double',
            'Suite' => 'Suite',
        ];
    }

    protected static function normalizeRoomType(?string $value): ?string
    {
        if (! $value) {
            return null;
        }

        $key = strtolower($value);

        return match ($key) {
            'single' => 'Single',
            'double' => 'Double',
            'suite' => 'Suite',
            default => null,
        };
    }

    protected static function expectedBedsForRoomType(?string $roomType): ?int
    {
        if (! $roomType) {
            return null;
        }

        $key = strtolower($roomType);

        return static::$roomTypeCapacityMap[$key] ?? null;
    }

    protected static function generateBedsPayload(int $count, ?string $roomNumber = null): array
    {
        $labelPrefix = $roomNumber ? "{$roomNumber}-Bed" : 'Bed';

        return collect(range(1, $count))
            ->map(fn ($index) => [
                'code' => sprintf('%s-%02d', $labelPrefix, $index),
                'status' => 'available',
            ])
            ->toArray();
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
}
