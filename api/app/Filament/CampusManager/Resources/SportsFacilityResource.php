<?php

namespace App\Filament\CampusManager\Resources;

use App\Filament\CampusManager\Resources\SportsFacilityResource\Pages;
use App\Filament\CampusManager\Resources\SportsFacilityResource\RelationManagers;
use App\Models\SportsFacility;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Schema;

class SportsFacilityResource extends Resource
{
    protected static ?string $model = SportsFacility::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Sports Management';

    protected static ?string $modelLabel = 'Sports Facility';

    protected static ?string $pluralModelLabel = 'Sports Facilities';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Facility Information')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Select::make('hostel_id')
                                    ->label('Hostel')
                                    ->options(fn (): array => self::getHostelOptions())
                                    ->required()
                                    ->searchable(),

                                Forms\Components\TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),

                                Forms\Components\Select::make('type')
                                    ->options([
                                        'sports' => 'Sports',
                                        'gym' => 'Gym',
                                        'courtyard' => 'Courtyard',
                                        'games_room' => 'Games Room',
                                        'multipurpose' => 'Multipurpose',
                                    ])
                                    ->required()
                                    ->default('sports'),

                                Forms\Components\TextInput::make('capacity')
                                    ->numeric()
                                    ->min(1)
                                    ->max(500)
                                    ->required()
                                    ->default(1),
                            ]),

                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\TimePicker::make('open_time')
                                    ->required(),

                                Forms\Components\TimePicker::make('close_time')
                                    ->required(),
                            ]),

                        Forms\Components\Textarea::make('description')
                            ->maxLength(1000)
                            ->columnSpanFull(),

                        Forms\Components\KeyValue::make('rules')
                            ->label('Rules & Restrictions')
                            ->keyLabel('Rule')
                            ->valueLabel('Description')
                            ->default([])
                            ->columnSpanFull(),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                // Ensure tenant scoping is applied at table level too
                $tenantId = self::resolveTenantId();

                if ($tenantId && self::hasColumn('sports_facilities', 'tenant_id')) {
                    try {
                        $query->where('tenant_id', $tenantId);
                    } catch (\Exception $e) {
                        \Log::warning('SportsFacilityResource: Table query tenant filter error', [
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
                
                return $query;
            })
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sports' => 'success',
                        'gym' => 'info',
                        'courtyard' => 'warning',
                        'games_room' => 'primary',
                        'multipurpose' => 'gray',
                    }),

                Tables\Columns\TextColumn::make('hostel.name')
                    ->label('Hostel')
                    ->sortable(),

                Tables\Columns\TextColumn::make('capacity')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('open_time')
                    ->time('H:i'),

                Tables\Columns\TextColumn::make('close_time')
                    ->time('H:i'),

                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('hostel_id')
                    ->label('Hostel')
                    ->options(fn (): array => self::getHostelOptions()),

                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'sports' => 'Sports',
                        'gym' => 'Gym',
                        'courtyard' => 'Courtyard',
                        'games_room' => 'Games Room',
                        'multipurpose' => 'Multipurpose',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueLabel('Only active facilities')
                    ->falseLabel('Only inactive facilities')
                    ->native(false),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalDescription('This will permanently delete the facility and cancel all future bookings.'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSportsFacilities::route('/'),
            'create' => Pages\CreateSportsFacility::route('/create'),
            'view' => Pages\ViewSportsFacility::route('/{record}'),
            'edit' => Pages\EditSportsFacility::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        try {
            $query = parent::getEloquentQuery();
            
            // Get tenant ID to scope queries
            $tenantId = self::resolveTenantId();
            
            // Explicitly scope by tenant_id if available
            if ($tenantId && self::hasColumn('sports_facilities', 'tenant_id')) {
                try {
                    $query->where('tenant_id', $tenantId);
                } catch (\Exception $e) {
                    \Log::error('SportsFacilityResource: Error filtering by tenant_id', [
                        'tenant_id' => $tenantId,
                        'error' => $e->getMessage(),
                    ]);
                    // Continue without tenant filtering if column doesn't exist
                }
            }
            
            return $query->with(['hostel']);
        } catch (\Exception $e) {
            \Log::error('SportsFacilityResource: Error in getEloquentQuery', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Return base query as fallback
            return parent::getEloquentQuery()->with(['hostel']);
        }
    }

    public static function canAccess(): bool
    {
        try {
            // Only visible if Sports add-on is enabled
            if (!config('features.sports_module', false)) {
                return false;
            }

            $user = auth()->user();
            return $user && $user->hasAnyRole(['Campus Manager', 'Super Admin']);
        } catch (\Exception $e) {
            \Log::error('SportsFacilityResource: canAccess error', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::canAccess();
    }

    private static function resolveTenantId(): ?string
    {
        $tenantId = null;
        try {
            if (function_exists('tenant') && tenant()) {
                $tenantId = (string) tenant()->id;
            }
        } catch (\Exception $e) {
            \Log::warning('SportsFacilityResource: tenant() error', [
                'error' => $e->getMessage(),
            ]);
        }

        if (!$tenantId && auth()->check() && auth()->user()?->tenant_id) {
            $tenantId = (string) auth()->user()->tenant_id;
        }

        return $tenantId ?: null;
    }

    private static function getHostelOptions(): array
    {
        try {
            $tenantId = self::resolveTenantId();
            $query = \App\Models\Hostel::query();

            if ($tenantId && self::hasColumn('hostels', 'tenant_id')) {
                $query->where('tenant_id', $tenantId);
            }

            return $query->pluck('name', 'id')->toArray();
        } catch (\Exception $e) {
            \Log::error('SportsFacilityResource: Failed to load hostels', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private static function hasColumn(string $table, string $column): bool
    {
        try {
            return Schema::hasColumn($table, $column);
        } catch (\Exception $e) {
            \Log::warning('SportsFacilityResource: Column check failed', [
                'table' => $table,
                'column' => $column,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
