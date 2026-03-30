<?php

namespace App\Filament\CampusManager\Resources;

use App\Domain\Attendance\Models\AttendanceSession;
use App\Filament\CampusManager\Resources\AttendanceSessionResource\Pages;
use App\Models\User;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter as TableFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AttendanceSessionResource extends Resource
{
    protected static ?string $model = AttendanceSession::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $tenantOwnershipRelationshipName = 'tenant';

    protected static ?string $navigationLabel = 'Attendance';

    protected static ?string $modelLabel = 'Attendance Session';

    protected static ?string $pluralModelLabel = 'Attendance Sessions';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationGroup = null; // Hidden from navigation

    public static function getNavigationGroup(): ?string
    {
        return null; // Hidden from navigation
    }

    public static function canViewAny(): bool
    {
        // Resource is accessible but hidden from navigation
        if (!config('features.attendance_module', true)) {
            return false;
        }

        $user = auth()->user();
        return $user && $user->hasAnyRole(['Warden', 'Campus Manager', 'Super Admin']);
    }

    public static function shouldRegisterNavigation(): bool
    {
        // Hide from sidebar navigation but keep resource accessible
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(120),
                Forms\Components\Select::make('kind')
                    ->options([
                        'roll_call' => 'Roll Call',
                        'event' => 'Event',
                        'night_check' => 'Night Check',
                    ])
                    ->required(),
                Forms\Components\DateTimePicker::make('scheduled_at')
                    ->required(),
                Forms\Components\Select::make('status')
                    ->options([
                        'scheduled' => 'Scheduled',
                        'open' => 'Open',
                        'closed' => 'Closed',
                    ])
                    ->required(),
            ]);
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
                        
                        return "P:{$present} A:{$absent} L:{$leave}";
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('hostel_id')
                    ->label('Hostel')
                    ->relationship('hostel', 'name')
                    ->options(function () {
                        $user = auth()->user();
                        // NOTE: tenant_id removed - hostels are in tenant DB, no tenant_id column
                        if ($user->hasRole('Super Admin') || $user->hasRole('Campus Manager')) {
                            return \App\Models\Hostel::pluck('name', 'id')->toArray();
                        }
                        
                        if ($user->hasRole('Warden')) {
                            // For now, show all hostels in tenant (warden assignment logic can be added later)
                            return \App\Models\Hostel::pluck('name', 'id')->toArray();
                        }
                        
                        return [];
                    })
                    ->preload(),
                TableFilter::make('date_range')
                    ->label('Date Range')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From Date')
                            ->default(now('Asia/Kolkata')->subDays(7)->toDateString())
                            ->displayFormat('d/m/Y'),
                        Forms\Components\DatePicker::make('to')
                            ->label('To Date')
                            ->default(now('Asia/Kolkata')->toDateString())
                            ->displayFormat('d/m/Y'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $from = !empty($data['from']) ? Carbon::parse($data['from'])->toDateString() : null;
                        $to = !empty($data['to']) ? Carbon::parse($data['to'])->toDateString() : null;

                        return $query
                            ->when(
                                $from,
                                fn (Builder $q): Builder => $q->whereDate('session_date', '>=', $from),
                            )
                            ->when(
                                $to,
                                fn (Builder $q): Builder => $q->whereDate('session_date', '<=', $to),
                            )
                            ->when(
                                !$from && !$to,
                                fn (Builder $q): Builder => $q->whereDate('session_date', '>=', Carbon::now('Asia/Kolkata')->subDays(7)->toDateString()),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if (!empty($data['from'])) {
                            $indicators[] = 'From: ' . Carbon::parse($data['from'])->format('M j, Y');
                        }
                        if (!empty($data['to'])) {
                            $indicators[] = 'To: ' . Carbon::parse($data['to'])->format('M j, Y');
                        }
                        return $indicators;
                    })
                    // NOTE: ->persistent() was removed here. That method does not exist
                    // in this version of Filament Tables Filter and caused a BadMethodCallException.
                    ,
            ], Tables\Enums\FiltersLayout::AboveContent)
            ->actions([
                Tables\Actions\Action::make('open')
                    ->label('Open')
                    ->icon('heroicon-o-eye')
                    ->url(fn (AttendanceSession $record): string => 
                        static::getUrl('view', ['record' => $record])
                    )
                    ->visible(fn (AttendanceSession $record): bool => 
                        $record->status === 'open'
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('scheduled_at', 'desc');
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
            'index' => Pages\ListAttendanceSessions::route('/'),
            'view' => Pages\ViewAttendanceSession::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();
        // NOTE: tenant_id removed - attendance_sessions are in tenant DB, no tenant_id column
        $query = parent::getEloquentQuery()
            ->where('status', 'closed'); // Only show completed sessions

        // Filter by hostel for Wardens (for now, show all hostels in tenant)
        if ($user->hasRole('Warden')) {
            // NOTE: tenant_id removed - hostels are in tenant DB, no tenant_id column
            $hostelIds = \App\Models\Hostel::pluck('id');
                
            $query->whereIn('hostel_id', $hostelIds);
        }

        return $query;
    }
}
