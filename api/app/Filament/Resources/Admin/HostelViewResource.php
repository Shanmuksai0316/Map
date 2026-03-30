<?php

namespace App\Filament\Resources\Admin;

use App\Filament\Resources\Admin\HostelViewResource\Pages;
use App\Models\Hostel;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;

class HostelViewResource extends Resource
{
    protected static ?string $model = Hostel::class;

    protected static ?string $navigationIcon = 'heroicon-o-home-modern';

    protected static ?string $navigationLabel = 'Hostels';

    protected static ?string $pluralLabel = 'All Hostels';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'hostels';

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('Super Admin');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['tenant', 'campus'])
            ->withCount('students')
            ->orderBy('tenant_id');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Tenant')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('tenant', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('gender_mode')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'male', 'boys' => 'Boys',
                        'female', 'girls' => 'Girls',
                        'coed', 'co-ed' => 'Co-ed',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'male', 'boys' => 'info',
                        'female', 'girls' => 'success',
                        'coed', 'co-ed' => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('floors_count')
                    ->label('Floors')
                    ->getStateUsing(fn (Hostel $record): int =>
                        \App\Models\Floor::where('hostel_id', $record->id)->count()
                    )
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('rooms_count')
                    ->label('Rooms')
                    ->counts('rooms')
                    ->sortable()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('beds_count')
                    ->label('Beds')
                    ->getStateUsing(fn (Hostel $record): int =>
                        \App\Models\RoomBed::where('hostel_id', $record->id)->count()
                    )
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('students_count')
                    ->label('Students')
                    ->sortable()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('occupancy')
                    ->label('Occupancy')
                    ->getStateUsing(function (Hostel $record): string {
                        $totalBeds = \App\Models\RoomBed::where('hostel_id', $record->id)->count();
                        $occupied = \App\Models\RoomBed::where('hostel_id', $record->id)
                            ->where('status', 'occupied')->count();
                        if ($totalBeds === 0) return '0%';
                        return round(($occupied / $totalBeds) * 100) . '%';
                    })
                    ->badge()
                    ->color(function (Hostel $record): string {
                        $totalBeds = \App\Models\RoomBed::where('hostel_id', $record->id)->count();
                        $occupied = \App\Models\RoomBed::where('hostel_id', $record->id)
                            ->where('status', 'occupied')->count();
                        if ($totalBeds === 0) return 'gray';
                        $pct = ($occupied / $totalBeds) * 100;
                        return ($pct >= 90) ? 'danger' : (($pct >= 75) ? 'warning' : 'success');
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label('Tenant')
                    ->relationship('tenant', 'name'),
                Tables\Filters\SelectFilter::make('gender_mode')
                    ->label('Gender Mode')
                    ->options([
                        'boys' => 'Boys',
                        'girls' => 'Girls',
                        'co-ed' => 'Co-ed',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;
                        if (!is_string($value) || $value === '') {
                            return $query;
                        }

                        return match ($value) {
                            'boys' => $query->whereIn('gender_mode', ['boys', 'male']),
                            'girls' => $query->whereIn('gender_mode', ['girls', 'female']),
                            'co-ed' => $query->whereIn('gender_mode', ['co-ed', 'coed']),
                            default => $query->where('gender_mode', $value),
                        };
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->defaultSort('tenant_id');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Hostel Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('tenant.name')
                            ->label('Tenant'),
                        Infolists\Components\TextEntry::make('campus.name')
                            ->label('Campus'),
                        Infolists\Components\TextEntry::make('code')
                            ->label('Code'),
                        Infolists\Components\TextEntry::make('name')
                            ->label('Name'),
                        Infolists\Components\TextEntry::make('gender_mode')
                            ->label('Gender Mode')
                            ->badge(),
                        Infolists\Components\TextEntry::make('curfew_time')
                            ->label('Curfew Time')
                            ->time(),
                        Infolists\Components\IconEntry::make('overnight_enabled')
                            ->label('Overnight Enabled')
                            ->boolean(),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime(),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHostelViews::route('/'),
            'view' => Pages\ViewHostelView::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}
