<?php

namespace App\Filament\CollegeMgmt\Resources;

use App\Filament\CollegeMgmt\Resources\StudentResource\Pages;
use App\Models\Student;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class StudentResource extends Resource
{
    protected static ?string $model = Student::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'Students';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        // Read-only - no form editing
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        $user = Auth::user();
        
        if (!$user) {
            return $table->modifyQueryUsing(fn (Builder $query) => $query->whereRaw('1 = 0'));
        }
        
        $tenantId = $user->tenant_id;

        return $table
            ->modifyQueryUsing(function (Builder $query) use ($tenantId) {
                $query->with(['user', 'roomAllocations.roomBed.room.hostel'])
                ->where('tenant_id', $tenantId);
                
                return $query;
            })
            ->columns([
                TextColumn::make('user.name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->join('users', 'students.user_id', '=', 'users.id')
                            ->orderBy('users.name', $direction)
                            ->select('students.*');
                    }),
                TextColumn::make('map_student_id')
                    ->label('Student ID')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('year_of_study')
                    ->label('Year')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('program')
                    ->label('Programme')
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('user.phone')
                    ->label('Contact')
                    ->searchable(),
                TextColumn::make('hostel_name')
                    ->label('Hostel')
                    ->getStateUsing(function (Student $record) {
                        $activeAllocation = $record->roomAllocations->firstWhere('is_active', true)
                            ?? $record->roomAllocations->first();
                        if (!$activeAllocation || !$activeAllocation->roomBed || !$activeAllocation->roomBed->room) {
                            return '--';
                        }
                        return $activeAllocation->roomBed->room->hostel->name ?? '--';
                    })
                    ->default('--')
                    ->sortable(false),
                TextColumn::make('room_number')
                    ->label('Room')
                    ->getStateUsing(function (Student $record) {
                        $activeAllocation = $record->roomAllocations->firstWhere('is_active', true)
                            ?? $record->roomAllocations->first();
                        if (!$activeAllocation || !$activeAllocation->roomBed || !$activeAllocation->roomBed->room) {
                            return '--';
                        }
                        $room = $activeAllocation->roomBed->room;
                        return $room->block_code . '-' . $room->floor_code . $room->room_no;
                    }),
            ])
            ->filters([
                SelectFilter::make('hostel')
                    ->label('Hostel')
                    ->options(function () use ($tenantId) {
                        return \App\Models\Hostel::where('tenant_id', $tenantId)
                            ->pluck('name', 'id')
                            ->toArray();
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) {
                            return $query;
                        }
                        return $query->whereHas('roomAllocations', function ($q) use ($data) {
                            $q->where('is_active', true)
                              ->where('hostel_id', $data['value']);
                        });
                    })
                    ->preload(),
                SelectFilter::make('year_of_study')
                    ->label('Year')
                    ->options(function () use ($tenantId) {
                        $years = Student::query()
                            ->where('tenant_id', $tenantId)
                            ->whereNotNull('year_of_study')
                            ->select('year_of_study')
                            ->distinct()
                            ->orderBy('year_of_study')
                            ->pluck('year_of_study')
                            ->mapWithKeys(fn ($year) => [(string) $year => $year . (in_array((int) $year, [1, 2, 3], true) ? ['st', 'nd', 'rd'][(int) $year - 1] : 'th') . ' Year'])
                            ->toArray();

                        return $years ?: [
                            '1' => '1st Year',
                            '2' => '2nd Year',
                            '3' => '3rd Year',
                            '4' => '4th Year',
                        ];
                    }),
            ])
            ->actions([
                ViewAction::make()
                    ->label('View'),
            ])
            ->defaultSort('students.id', 'desc')
            ->paginated([10, 25, 50, 100]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStudents::route('/'),
            'view' => Pages\ViewStudent::route('/{record}'),
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
