<?php

namespace App\Filament\CampusManager\Resources;

use App\Filament\CampusManager\Resources\AssignedRoomResource\Pages;
use App\Models\Room;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class AssignedRoomResource extends Resource
{
    protected static ?string $model = Room::class;

    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationGroup = 'Rooms & Allocation';

    protected static ?string $navigationLabel = 'Assigned Rooms';

    protected static ?string $modelLabel = 'Assigned Room';

    protected static ?string $pluralModelLabel = 'Assigned Rooms';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'rooms/assigned';

    public static function form(Form $form): Form
    {
        return RoomResource::form($form);
    }

    public static function table(Table $table): Table
    {
        try {
            $tenantId = static::getTenantId();
            
            if (!$tenantId) {
                \Log::warning('AssignedRoomResource: No tenant ID found', [
                    'user_id' => auth()->id(),
                ]);
            }
            
            return $table
                ->modifyQueryUsing(function (Builder $query) use ($tenantId) {
                    if ($tenantId) {
                        $query->where('rooms.tenant_id', $tenantId);
                    }
                    
                    // Only rooms with at least one occupied bed (scoped by tenant)
                    // The beds relationship query is already scoped by TenantScoped trait,
                    // but we explicitly add tenant_id filter to ensure tenant isolation
                    return $query->whereHas('beds', function ($q) use ($tenantId) {
                        $q->where('status', 'occupied');
                        if ($tenantId) {
                            $q->where('tenant_id', $tenantId);
                        }
                    })->with(['hostel', 'beds']);
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

                Tables\Columns\TextColumn::make('occupancy')
                    ->label('Occupancy')
                    ->getStateUsing(function (Room $record) {
                        $tenantId = static::getTenantId();
                        $bedQuery = $record->beds();
                        if ($tenantId) {
                            $bedQuery->where('tenant_id', $tenantId);
                        }
                        $totalBeds = $bedQuery->count();
                        $occupiedBeds = (clone $bedQuery)->where('status', 'occupied')->count();
                        return "{$occupiedBeds}/{$totalBeds}";
                    })
                    ->badge()
                    ->color('success'),

                // NOTE: Uses 'roomBed' relationship (NOT 'bed') because RoomAllocation
                // belongs to RoomBed via the roomBed() method. Using 'bed' would fail
                // as that relationship does not exist on the RoomAllocation model.
                Tables\Columns\TextColumn::make('assigned_date')
                    ->label('Assigned Date')
                    ->getStateUsing(function (Room $record) {
                        $tenantId = static::getTenantId();
                        // Get earliest allocation date for this room
                        $allocationQuery = \App\Models\RoomAllocation::query()
                            ->whereHas('roomBed', function ($q) use ($record) {
                                $q->where('room_id', $record->id);
                            })
                            ->where('is_active', true);
                        if ($tenantId) {
                            $allocationQuery->where('tenant_id', $tenantId);
                        }
                        $allocation = $allocationQuery
                            ->orderBy('effective_from', 'asc')
                            ->first();
                        return $allocation?->effective_from?->format('d M Y') ?? '—';
                    }),

                Tables\Columns\TextColumn::make('occupants')
                    ->label('Occupants')
                    ->getStateUsing(function (Room $record) {
                        $tenantId = static::getTenantId();
                        $bedQuery = $record->beds()->where('status', 'occupied');
                        if ($tenantId) {
                            $bedQuery->where('tenant_id', $tenantId);
                        }
                        $occupiedBeds = $bedQuery->count();
                        return "{$occupiedBeds} student(s)";
                    }),
            ])
            ->filters([
                SelectFilter::make('hostel_id')
                    ->label('All Rooms')
                    ->options(function () {
                        $tenantId = static::getTenantId();
                        $query = \App\Models\Hostel::query();
                        if ($tenantId) {
                            $query->where('tenant_id', $tenantId);
                        }
                        return $query->orderBy('name')->pluck('name', 'id')->toArray();
                    })
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
            ])
            ->bulkActions([]);
        } catch (\Exception $e) {
            \Log::error('AssignedRoomResource: Error in table()', [
                'error' => $e->getMessage(),
                'trace' => substr($e->getTraceAsString(), 0, 500),
            ]);
            
            // Return a safe table query that won't crash
            return $table
                ->query(Room::query()->whereRaw('1 = 0')) // Empty query
                ->columns([])
                ->emptyStateHeading('Error loading rooms')
                ->emptyStateDescription('Please refresh the page or contact support if the issue persists.');
        }
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssignedRooms::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $tenantId = static::getTenantId();
        
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        // Apply hostel switcher scope
        $activeHostelId = session('active_hostel_id');
        if ($activeHostelId) {
            $query->where('hostel_id', $activeHostelId);
        }
        
        return $query->with(['hostel']);
    }

    protected static function getTenantId(): ?string
    {
        // Prioritize tenant context from subdomain
        try {
            if (function_exists('tenant') && tenant()) {
                return tenant()->id;
            }
        } catch (\Exception $e) {
            // tenant() might not be available
        }

        // Fallback to user's tenant_id
        if (Auth::check() && Auth::user()->tenant_id) {
            return Auth::user()->tenant_id;
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

