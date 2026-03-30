<?php

namespace App\Filament\CampusManager\Resources;

use App\Filament\CampusManager\Resources\AssignedStudentResource\Pages;
use App\Http\Middleware\SetPostgresSessionTenant;
use App\Models\Student;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class AssignedStudentResource extends Resource
{
    protected static ?string $model = Student::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-plus';

    protected static ?string $navigationGroup = 'Student Management';

    protected static ?string $navigationLabel = 'Assigned Students';

    protected static ?string $modelLabel = 'Assigned Student';

    protected static ?string $pluralModelLabel = 'Assigned Students';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'students/assigned';

    public static function form(Form $form): Form
    {
        return StudentResource::form($form);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $tenantId = static::resolveTenantId();

                if ($tenantId) {
                    SetPostgresSessionTenant::setTenantSessionVariable($tenantId);
                    $query->where('students.tenant_id', $tenantId);
                }

                // Apply hostel switcher scope from session
                $activeHostelId = session('active_hostel_id');
                if ($activeHostelId) {
                    $query->where('students.hostel_id', $activeHostelId);
                }

                // Only assigned students (have active room allocation)
                return $query->with(['hostel', 'roomAllocations'])
                    ->whereHas('roomAllocations', function ($q) {
                        $q->where('is_active', true);
                    });
            })
            ->columns([
                Tables\Columns\TextColumn::make('user_name')
                    ->label('Name')
                    ->getStateUsing(function ($record) {
                        try {
                            // Use default connection (tenant context handles the connection)
                            $user = \App\Models\User::find($record->user_id);
                            return $user ? $user->name : ($record->full_name ?? 'Unknown');
                        } catch (\Exception $e) {
                            \Log::warning('AssignedStudentResource: Failed to load user', [
                                'user_id' => $record->user_id,
                                'error' => $e->getMessage(),
                            ]);
                            return $record->full_name ?? 'Unknown';
                        }
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where('full_name', 'ilike', "%{$search}%");
                    }),

                Tables\Columns\TextColumn::make('student_uid')
                    ->label('Student ID')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('year_of_study')
                    ->label('Year')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? "Year {$state}" : '—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('program')
                    ->label('Programme')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('user_phone')
                    ->label('Contact')
                    ->getStateUsing(function ($record) {
                        try {
                            // Use default connection (tenant context handles the connection)
                            $user = \App\Models\User::find($record->user_id);
                            return $user ? $user->phone : ($record->mobile_number ?? '—');
                        } catch (\Exception $e) {
                            \Log::warning('AssignedStudentResource: Failed to load user phone', [
                                'user_id' => $record->user_id,
                                'error' => $e->getMessage(),
                            ]);
                            return $record->mobile_number ?? '—';
                        }
                    }),

                Tables\Columns\TextColumn::make('hostel_name')
                    ->label('Hostel')
                    ->getStateUsing(function (Student $record) {
                        $allocation = $record->roomAllocations?->firstWhere('is_active', true);
                        return $allocation?->hostel?->name ?? '—';
                    }),

                Tables\Columns\TextColumn::make('allocated_room')
                    ->label('Room')
                    ->getStateUsing(function (Student $record) {
                        $allocation = $record->roomAllocations?->firstWhere('is_active', true);
                        if (!$allocation) return '—';
                        
                        $bed = \App\Models\RoomBed::withoutGlobalScopes()->find($allocation->room_bed_id);
                        if (!$bed) return '—';
                        
                        $room = \App\Models\Room::withoutGlobalScopes()->find($bed->room_id);
                        return $room ? $room->number : '—';
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('roomAllocations', function ($q) use ($search) {
                            $q->whereHas('bed', function ($bedQ) use ($search) {
                                $bedQ->whereHas('room', function ($roomQ) use ($search) {
                                    $roomQ->where('number', 'ilike', "%{$search}%");
                                });
                            });
                        });
                    }),

                Tables\Columns\TextColumn::make('allocation_status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn () => 'Assigned')
                    ->color('success'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('hostel_id')
                    ->label('All Hostels')
                    ->options(function () {
                        $tenantId = static::resolveTenantId();
                        $query = \App\Models\Hostel::query();
                        if ($tenantId) {
                            $query->where('tenant_id', $tenantId);
                        }
                        return $query->pluck('name', 'id')->toArray();
                    })
                    ->searchable()
                    ->preload()
                    ->placeholder('All Hostels')
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) return $query;
                        return $query->whereHas('roomAllocations', function ($q) use ($data) {
                            $q->where('is_active', true)->where('hostel_id', $data['value']);
                        });
                    }),

                Tables\Filters\SelectFilter::make('year_of_study')
                    ->label('All Years')
                    ->options([
                        '1' => '1st Year',
                        '2' => '2nd Year',
                        '3' => '3rd Year',
                        '4' => '4th Year',
                        '5' => '5th Year',
                    ])
                    ->placeholder('All Years'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssignedStudents::route('/'),
            'view' => Pages\ViewAssignedStudent::route('/{record}'),
            'edit' => Pages\EditAssignedStudent::route('/{record}/edit'),
        ];
    }

    protected static function resolveTenantId(): ?string
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
        if (auth()->check() && auth()->user()?->tenant_id) {
            return auth()->user()->tenant_id;
        }

        return null;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        
        // Apply tenant scoping at the base query level
        $tenantId = static::resolveTenantId();
        
        if ($tenantId) {
            SetPostgresSessionTenant::setTenantSessionVariable($tenantId);
            $query->where('tenant_id', $tenantId);
        }
        
        return $query;
    }

    public static function canAccess(): bool
    {
        try {
            /** @var \App\Models\User|null $user */
            $user = Auth::user();
            return Auth::check() && $user && $user->hasAnyRole(['Campus Manager', 'Super Admin', 'Rector']);
        } catch (\Exception $e) {
            \Log::error('AssignedStudentResource: canAccess error', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}

