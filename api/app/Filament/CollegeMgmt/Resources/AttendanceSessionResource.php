<?php

namespace App\Filament\CollegeMgmt\Resources;

use App\Domain\Attendance\Models\AttendanceSession;
use App\Filament\CollegeMgmt\Resources\AttendanceSessionResource\Pages;
use Carbon\Carbon;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class AttendanceSessionResource extends Resource
{
    protected static ?string $model = AttendanceSession::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Attendance';

    protected static ?string $modelLabel = 'Attendance Session';

    protected static ?string $pluralModelLabel = 'Attendance Sessions';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        // Read-only - no form editing
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('hostel.name')
                    ->label('Hostel')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('session_date')
                    ->label('Date')
                    ->date('M j, Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('window')
                    ->label('Window')
                    ->getStateUsing(function (AttendanceSession $record): string {
                        $openAt = $record->metadata['open_at'] ?? null;
                        $closeAt = $record->metadata['close_at'] ?? null;
                        
                        if (!$openAt || !$closeAt) {
                            return 'N/A';
                        }
                        
                        $open = Carbon::parse($openAt)->format('H:i');
                        $close = Carbon::parse($closeAt)->format('H:i');
                        
                        return "{$open} - {$close}";
                    }),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'open',
                        'warning' => 'scheduled',
                        'gray' => 'closed',
                    ])
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                Tables\Columns\TextColumn::make('progress')
                    ->label('Progress')
                    ->getStateUsing(function (AttendanceSession $record): string {
                        $present = $record->metadata['present_count'] ?? 0;
                        $absent = $record->metadata['absent_count'] ?? 0;
                        $leave = $record->metadata['leave_count'] ?? 0;
                        $unmarked = $record->metadata['unmarked_count'] ?? 0;
                        
                        return "Present: {$present} | Absent: {$absent} | Leave: {$leave} | Unmarked: {$unmarked}";
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('hostel_id')
                    ->label('Hostel')
                    ->relationship('hostel', 'name'),
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
                                fn (Builder $query, $date): Builder => $query->whereDate('session_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('session_date', '<=', $date),
                            );
                    }),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'scheduled' => 'Scheduled',
                        'open' => 'Open',
                        'closed' => 'Closed',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->defaultSort('session_date', 'desc')
            ->paginated([10, 25, 50, 100]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAttendanceSessions::route('/'),
            'view' => Pages\ViewAttendanceSession::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $tenantId = Auth::user()?->tenant_id;

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return $query;
    }

    public static function canAccess(): bool
    {
        if (!config('features.attendance_module', true)) {
            return false;
        }

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
