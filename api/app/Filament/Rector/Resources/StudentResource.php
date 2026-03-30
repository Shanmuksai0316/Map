<?php

namespace App\Filament\Rector\Resources;

use App\Filament\Rector\Resources\StudentResource\Pages;
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
use Illuminate\Support\Facades\DB;

class StudentResource extends Resource
{
    protected static ?string $model = Student::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

    protected static ?string $navigationLabel = 'Students';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([
            // Form is view-only, no editing allowed for Rector
        ]);
    }

    public static function table(Table $table): Table
    {
        $user = Auth::user();
        
        if (!$user) {
            // If no user, return empty table
            return $table->modifyQueryUsing(fn (Builder $query) => $query->whereRaw('1 = 0'));
        }
        
        $tenantId = $user->tenant_id;

        // Get rector's assigned hostel IDs from staff_assignments
        // If Rector has no assignments, show all students for their tenant
        $assignedHostelIds = [];
        if ($user->id && $tenantId) {
            $assignedHostelIds = DB::table('staff_assignments')
                ->where('user_id', $user->id)
                ->where('tenant_id', $tenantId)
                ->whereNull('revoked_at')
                ->whereNotNull('hostel_id')
                ->pluck('hostel_id')
                ->toArray();
        }

        return $table
            ->modifyQueryUsing(function (Builder $query) use ($tenantId, $assignedHostelIds) {
                $query->with(['user', 'roomAllocations' => function ($q) {
                    $q->where('is_active', true)
                      ->with(['roomBed.room.hostel']);
                }])
                ->where('tenant_id', $tenantId);
                
                if (!empty($assignedHostelIds)) {
                    // Only show students in assigned hostels
                    $query->whereHas('roomAllocations', function ($q2) use ($assignedHostelIds) {
                        $q2->where('is_active', true)
                           ->whereIn('hostel_id', $assignedHostelIds);
                    });
                }
                
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
                        $activeAllocation = $record->roomAllocations()->where('is_active', true)->first();
                        if (!$activeAllocation || !$activeAllocation->roomBed) {
                            return '--';
                        }
                        return $activeAllocation->roomBed->room->hostel->name ?? '--';
                    })
                    ->default('--')
                    ->sortable(false),
                TextColumn::make('room_number')
                    ->label('Room')
                    ->getStateUsing(function (Student $record) {
                        $activeAllocation = $record->roomAllocations()->where('is_active', true)->first();
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
                SelectFilter::make('year')
                    ->label('Year')
                    ->options([
                        '1st Year' => '1st Year',
                        '2nd Year' => '2nd Year',
                        '3rd Year' => '3rd Year',
                        '4th Year' => '4th Year',
                    ]),
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
        return $user && $user->hasRole('Rector');
    }

    public static function canCreate(): bool
    {
        return false; // Rector cannot create students
    }

    public static function canEdit($record): bool
    {
        return false; // Rector cannot edit students
    }

    public static function canDelete($record): bool
    {
        return false; // Rector cannot delete students
    }
}

