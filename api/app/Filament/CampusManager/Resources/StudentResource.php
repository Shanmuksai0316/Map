<?php

namespace App\Filament\CampusManager\Resources;

use App\Filament\CampusManager\Resources\StudentResource\Pages;
use App\Http\Middleware\SetPostgresSessionTenant;
use Illuminate\Support\Facades\Log;
use App\Models\Hostel;
use App\Models\Student;
use App\Models\User;
use App\Services\Students\StudentLifecycleService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class StudentResource extends Resource
{
    protected static ?string $model = Student::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Student Management';

    protected static ?string $navigationLabel = 'All Students';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // A. Basic Information
                Forms\Components\Section::make('Basic Information')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('full_name')
                            ->label('Full Name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email_address')
                            ->label('Email ID')
                            ->email()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('mobile_number')
                            ->label('Mobile Number')
                            ->tel()
                            ->required()
                            ->maxLength(20),

                        Forms\Components\Select::make('gender')
                            ->label('Gender')
                            ->options([
                                'male' => 'Male',
                                'female' => 'Female',
                                'other' => 'Other',
                            ])
                            ->required(),

                        Forms\Components\DatePicker::make('date_of_birth')
                            ->label('Date of Birth')
                            ->maxDate(now()->subYears(15))
                            ->helperText('Student must be at least 15 years old')
                            ->required(false)
                            ->native(false),
                    ]),

                // B. Academic Details
                Forms\Components\Section::make('Academic Details')
                    ->columns(4)
                    ->schema([
                        Forms\Components\TextInput::make('map_id')
                            ->label('MAP ID')
                            ->unique(ignoreRecord: true)
                            ->disabled(fn ($record) => $record !== null)
                            ->dehydrated(fn ($record) => $record === null),

                        Forms\Components\TextInput::make('erp_number')
                            ->label('ERP Number')
                            ->unique(ignoreRecord: true),

                        Forms\Components\TextInput::make('department')
                            ->label('Department'),

                        Forms\Components\Select::make('year_of_study')
                            ->label('Year')
                            ->options([
                                '1' => '1st Year',
                                '2' => '2nd Year',
                                '3' => '3rd Year',
                                '4' => '4th Year',
                                '5' => '5th Year',
                            ]),
                    ]),

                // C. Hostel Allocation
                Forms\Components\Section::make('Hostel Allocation')
                    ->columns(4)
                    ->schema([
                        Forms\Components\Select::make('hostel_id')
                            ->label('Assigned Hostel')
                            ->options(fn () => static::hostelOptions())
                            ->searchable()
                            ->preload(),

                        Forms\Components\Placeholder::make('room_number_display')
                            ->label('Room Number')
                            ->content(fn ($record) => static::getRoomNumberForStudent($record) ?? '—'),

                        Forms\Components\Placeholder::make('room_capacity_display')
                            ->label('Room Capacity')
                            ->content(fn ($record) => static::getRoomCapacityForStudent($record) ?? '—'),

                        Forms\Components\Placeholder::make('allocation_status_display')
                            ->label('Current Status')
                            ->content(fn ($record) => $record?->roomAllocations?->isNotEmpty() ? 'Assigned' : 'Unassigned'),
                    ]),

                // D. Emergency Contacts & Parent Info
                Forms\Components\Section::make('Emergency Contacts & Parent Info')
                    ->schema([
                        Forms\Components\Fieldset::make('Parent Information')
                            ->columns(4)
                            ->schema([
                                Forms\Components\TextInput::make('father_name')
                                    ->label('Father Name'),

                                Forms\Components\TextInput::make('father_mobile_number')
                                    ->label('Father Number')
                                    ->tel(),

                                Forms\Components\TextInput::make('mother_name')
                                    ->label('Mother Name'),

                                Forms\Components\TextInput::make('mother_mobile_number')
                                    ->label('Mother Number')
                                    ->tel(),
                            ]),

                        Forms\Components\Fieldset::make('Local Guardian')
                            ->columns(3)
                            ->schema([
                                Forms\Components\TextInput::make('local_guardian_name')
                                    ->label('Guardian Name'),

                                Forms\Components\TextInput::make('local_guardian_contact')
                                    ->label('Contact Number')
                                    ->tel(),

                                Forms\Components\TextInput::make('local_relationship')
                                    ->label('Relationship'),

                                Forms\Components\Textarea::make('local_address')
                                    ->label('Local Address')
                                    ->rows(2)
                                    ->columnSpan(3),
                            ]),
                    ]),

                // E. Medical Information
                Forms\Components\Section::make('Medical Information')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('blood_group')
                            ->label('Blood Group')
                            ->options([
                                'A+' => 'A+',
                                'A-' => 'A-',
                                'B+' => 'B+',
                                'B-' => 'B-',
                                'O+' => 'O+',
                                'O-' => 'O-',
                                'AB+' => 'AB+',
                                'AB-' => 'AB-',
                            ])
                            ->searchable(),

                        Forms\Components\Textarea::make('medical_information')
                            ->label('Medical Conditions / Allergies')
                            ->placeholder('Enter any medical conditions, allergies, disabilities, or medications')
                            ->rows(3),
                    ]),
            ]);
    }

    protected static function getRoomNumberForStudent($record): ?string
    {
        if (!$record) return null;
        $allocation = $record->roomAllocations?->firstWhere('is_active', true);
        if (!$allocation || !$allocation->room_bed_id) return null;
        $bed = \App\Models\RoomBed::withoutGlobalScopes()->find($allocation->room_bed_id);
        if (!$bed) return null;
        $room = \App\Models\Room::withoutGlobalScopes()->find($bed->room_id);
        return $room?->number;
    }

    protected static function getRoomCapacityForStudent($record): ?string
    {
        if (!$record) return null;
        $allocation = $record->roomAllocations?->firstWhere('is_active', true);
        if (!$allocation || !$allocation->room_bed_id) return null;
        $bed = \App\Models\RoomBed::withoutGlobalScopes()->find($allocation->room_bed_id);
        if (!$bed) return null;
        $room = \App\Models\Room::withoutGlobalScopes()->find($bed->room_id);
        return $room?->capacity ? (string) $room->capacity : null;
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

                return $query->with(['hostel', 'preferredHostel', 'roomAllocations']);
            })
            ->columns([
                // Name
                Tables\Columns\TextColumn::make('user_name')
                    ->label('Name')
                    ->getStateUsing(function ($record) {
                        try {
                            // Use default connection (tenant context handles the connection)
                            $user = \App\Models\User::find($record->user_id);
                            return $user ? $user->name : ($record->full_name ?? 'Unknown');
                        } catch (\Exception $e) {
                            \Log::warning('StudentResource: Failed to load user', [
                                'user_id' => $record->user_id,
                                'error' => $e->getMessage(),
                            ]);
                            return $record->full_name ?? 'Unknown';
                        }
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where('full_name', 'like', "%{$search}%");
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('full_name', $direction);
                    }),

                // Student ID (student_uid)
                Tables\Columns\TextColumn::make('student_uid')
                    ->label('Student ID')
                    ->searchable()
                    ->sortable(),

                // Year
                Tables\Columns\TextColumn::make('year_of_study')
                    ->label('Year')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? "Year {$state}" : '—')
                    ->sortable(),

                // Programme
                Tables\Columns\TextColumn::make('program')
                    ->label('Programme')
                    ->searchable()
                    ->sortable(),

                // Contact
                Tables\Columns\TextColumn::make('user_phone')
                    ->label('Contact')
                    ->getStateUsing(function ($record) {
                        $user = \App\Models\User::find($record->user_id);
                        return $user ? $user->phone : ($record->mobile_number ?? '—');
                    }),

                // Hostel
                Tables\Columns\TextColumn::make('hostel_name')
                    ->label('Hostel')
                    ->getStateUsing(function (Student $record) {
                        $allocation = $record->roomAllocations?->firstWhere('is_active', true);
                        if (!$allocation) return '—';
                        return $allocation->hostel?->name ?? '—';
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('hostel_id', $direction);
                    }),

                // Room
                Tables\Columns\TextColumn::make('allocated_room')
                    ->label('Room')
                    ->getStateUsing(function (Student $record) {
                        $allocation = $record->roomAllocations?->firstWhere('is_active', true) ?? $record->roomAllocations?->first();
                        if (!$allocation) return '—';
                        
                        $bed = \App\Models\RoomBed::withoutGlobalScopes()->find($allocation->room_bed_id);
                        if (!$bed) return '—';
                        
                        $room = \App\Models\Room::withoutGlobalScopes()->find($bed->room_id);
                        return $room ? $room->number : '—';
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        // Search through room allocations
                        return $query->whereHas('roomAllocations', function ($q) use ($search) {
                            $q->whereHas('roomBed', function ($bedQ) use ($search) {
                                $bedQ->whereHas('room', function ($roomQ) use ($search) {
                                    $roomQ->where('number', 'like', "%{$search}%");
                                });
                            });
                        });
                    }),

                // Status (Assigned/Unassigned)
                Tables\Columns\TextColumn::make('allocation_status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn (Student $record) => $record->roomAllocations?->isNotEmpty() ? 'Assigned' : 'Unassigned')
                    ->color(fn (Student $record) => $record->roomAllocations?->isNotEmpty() ? 'success' : 'warning'),
            ])
            ->filters([
                // All Hostels dropdown
                Tables\Filters\SelectFilter::make('hostel_id')
                    ->label('All Hostels')
                    ->options(function () {
                        return \App\Models\Hostel::pluck('name', 'id')->toArray();
                    })
                    ->searchable()
                    ->preload()
                    ->placeholder('All Hostels')
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) return $query;
                        return $query->whereHas('roomAllocations', function ($q) use ($data) {
                            $q->where('is_active', true)
                              ->where('hostel_id', $data['value']);
                        });
                    }),

                // All Years dropdown
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

                // All Status dropdown (Assigned/Unassigned)
                Tables\Filters\SelectFilter::make('allocation_status')
                    ->label('All Status')
                    ->options([
                        'assigned' => 'Assigned',
                        'unassigned' => 'Unassigned',
                    ])
                    ->placeholder('All Status')
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) return $query;
                        
                        if ($data['value'] === 'assigned') {
                            return $query->whereHas('roomAllocations', function ($q) {
                                $q->where('is_active', true);
                            });
                        }
                        
                        if ($data['value'] === 'unassigned') {
                            return $query->whereDoesntHave('roomAllocations', function ($q) {
                                $q->where('is_active', true);
                            });
                        }
                        
                        return $query;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('activate')
                    ->label('Activate')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Student $record) {
                        $user = \App\Models\User::find($record->user_id);
                        if ($user) {
                            $user->update(['status' => 'Active']);
                        }
                    })
                    ->visible(function (Student $record) {
                        $user = \App\Models\User::find($record->user_id);
                        return $user && $user->status !== 'Active';
                    }),
                Tables\Actions\Action::make('deactivate')
                    ->label('Deactivate')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (Student $record) {
                        $user = \App\Models\User::find($record->user_id);
                        if ($user) {
                            $user->update(['status' => 'Inactive']);
                        }
                    })
                    ->visible(function (Student $record) {
                        $user = \App\Models\User::find($record->user_id);
                        return $user && $user->status === 'Active';
                    }),
                Tables\Actions\Action::make('archive_student')
                    ->label('Archive')
                    ->icon('heroicon-o-archive-box')
                    ->color('warning')
                    ->visible(fn (Student $record) => $record->archived_at === null)
                    ->requiresConfirmation()
                    ->modalHeading('Archive Student')
                    ->form([
                        Forms\Components\DatePicker::make('archived_at')
                            ->label('Archive Date')
                            ->required()
                            ->default(now())
                            ->maxDate(now()),
                        Forms\Components\Textarea::make('archived_reason')
                            ->label('Reason')
                            ->required()
                            ->rows(3)
                            ->maxLength(500),
                    ])
                    ->action(fn (Student $record, array $data) => static::archiveStudentRecord($record, $data)),
                Tables\Actions\Action::make('restore_student')
                    ->label('Restore')
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->color('success')
                    ->visible(fn (Student $record) => $record->archived_at !== null)
                    ->requiresConfirmation()
                    ->modalHeading('Restore Student')
                    ->action(fn (Student $record) => static::restoreStudentRecord($record)),
                Tables\Actions\Action::make('allocate_room')
                    ->label('Allocate Room')
                    ->icon('heroicon-o-home')
                    ->color('success')
                    ->modalHeading('Allocate Room for Student')
                    ->modalDescription('Select an available room and bed for this student.')
                    ->disabled(fn () => static::defaultHostelId() === null)
                    ->tooltip(fn () => static::defaultHostelId() === null ? 'Create a hostel with available rooms before allocating beds.' : null)
                    ->form([
                        Forms\Components\Placeholder::make('hostel_name')
                            ->label('Hostel')
                            ->content(fn () => static::defaultHostel()?->name ?? 'No hostel available')
                            ->visible(fn () => static::defaultHostel() !== null),
                        Forms\Components\Hidden::make('hostel_id')
                            ->default(fn () => static::defaultHostel()?->id)
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('room_id', null)),
                        Forms\Components\Select::make('room_id')
                            ->label('Room')
                            ->options(function (Forms\Get $get) {
                                $hostelId = $get('hostel_id');
                                if (!$hostelId) {
                                    return [];
                                }
                                $tenantId = auth()->user()?->tenant_id ?? (tenancy()->tenant?->id ?? null);
                                return \App\Models\Room::query()
                                    ->where('tenant_id', $tenantId)
                                    ->where('hostel_id', $hostelId)
                                    ->where('is_active', true)
                                    ->orderBy('number')
                                    ->get()
                                    ->mapWithKeys(fn ($room) => [$room->id => "{$room->number} (Floor: {$room->floor_code}, Block: {$room->block_code})"])
                                    ->toArray();
                            })
                            ->required()
                            ->searchable()
                            ->reactive()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('room_bed_id', null)),
                        Forms\Components\Select::make('room_bed_id')
                            ->label('Bed')
                            ->options(function (Forms\Get $get) {
                                $roomId = $get('room_id');
                                if (!$roomId) {
                                    return [];
                                }
                                $tenantId = auth()->user()?->tenant_id ?? (tenancy()->tenant?->id ?? null);
                                return \App\Models\RoomBed::query()
                                    ->withoutGlobalScopes()
                                    ->where(function ($query) use ($tenantId) {
                                        $query->where('tenant_id', $tenantId)
                                            ->orWhereNull('tenant_id');
                                    })
                                    ->where('room_id', $roomId)
                                    ->where('status', 'available')
                                    ->orderBy('code')
                                    ->get()
                                    ->mapWithKeys(fn ($bed) => [$bed->id => "{$bed->code} (Available)"])
                                    ->toArray();
                            })
                            ->required()
                            ->searchable()
                            ->helperText('Only available beds are shown.'),
                        Forms\Components\DatePicker::make('effective_from')
                            ->label('Allocation Start Date')
                            ->required()
                            ->default(now())
                            ->maxDate(now()->addYear()),
                        Forms\Components\Textarea::make('note')
                            ->label('Notes')
                            ->maxLength(500)
                            ->rows(3),
                    ])
                    ->action(function (Student $record, array $data) {
                        static::allocateRoom($record, $data);
                    })
                    ->visible(fn (Student $record) => !$record->roomAllocations()->where('is_active', true)->exists()),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    protected static function hostelOptions(): array
    {
        $tenantId = auth()->user()?->tenant_id ?? (tenancy()->tenant?->id ?? null);
        
        return Hostel::query()
            ->when($tenantId, fn ($query) => $query->where('tenant_id', $tenantId))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    protected static function defaultHostel(): ?Hostel
    {
        $tenantId = auth()->user()?->tenant_id ?? (tenancy()->tenant?->id ?? null);

        if (! $tenantId) {
            return null;
        }

        return Hostel::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->first();
    }

    protected static function defaultHostelId(): ?int
    {
        return static::defaultHostel()?->id;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStudents::route('/'),
            'create' => Pages\CreateStudent::route('/create'),
            'view' => Pages\ViewStudent::route('/{record}'),
            'edit' => Pages\EditStudent::route('/{record}/edit'),
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
        $query = Student::query();
        
        // Apply tenant scoping at the base query level
        $tenantId = static::resolveTenantId();
        
        if ($tenantId) {
            SetPostgresSessionTenant::setTenantSessionVariable($tenantId);
            $query->where('tenant_id', $tenantId);
        }
        
        return $query;
    }

    protected static function archiveStudentRecord(Student $student, array $data): void
    {
        $archiveAt = Carbon::parse($data['archived_at'] ?? now());
        $reason = $data['archived_reason'] ?? null;

        app(StudentLifecycleService::class)
            ->archive($student, $archiveAt, $reason);

        Notification::make()
            ->title('Student archived')
            ->success()
            ->send();
    }

    protected static function restoreStudentRecord(Student $student): void
    {
        app(StudentLifecycleService::class)->restore($student);

        Notification::make()
            ->title('Student restored')
            ->success()
            ->send();
    }

    protected static function allocateRoom(Student $student, array $data): void
    {
        $tenantId = auth()->user()?->tenant_id ?? (tenancy()->tenant?->id ?? null);
        
        if (!$tenantId) {
            Notification::make()
                ->title('Error')
                ->body('Tenant ID is required.')
                ->danger()
                ->send();
            return;
        }

        $bed = \App\Models\RoomBed::query()
            ->withoutGlobalScopes()
            ->where(function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId)
                    ->orWhereNull('tenant_id');
            })
            ->whereKey($data['room_bed_id'])
            ->where('status', 'available')
            ->first();

        if (!$bed) {
            Notification::make()
                ->title('Error')
                ->body('The selected bed is not available or does not exist.')
                ->danger()
                ->send();
            return;
        }

        // Get hostel_id from bed or room
        $hostelId = $bed->hostel_id;
        if (!$hostelId && $bed->room_id) {
            $room = \App\Models\Room::query()
                ->withoutGlobalScopes()
                ->where(function ($query) use ($tenantId) {
                    $query->where('tenant_id', $tenantId)
                        ->orWhereNull('tenant_id');
                })
                ->find($bed->room_id);
            if ($room) {
                $hostelId = $room->hostel_id;
            }
        }
        
        // Fallback to hostel_id from form data if still not found
        if (!$hostelId && isset($data['hostel_id'])) {
            $hostelId = $data['hostel_id'];
        }

        if (!$hostelId) {
            Notification::make()
                ->title('Error')
                ->body('Unable to determine hostel for this bed.')
                ->danger()
                ->send();
            return;
        }

        $effectiveFrom = \Illuminate\Support\Carbon::parse($data['effective_from'] ?? now());

        // Deactivate any existing active allocations for this student
        \App\Models\RoomAllocation::query()
            ->where('tenant_id', $tenantId)
            ->where('student_id', $student->id)
            ->where('is_active', true)
            ->update([
                'is_active' => false,
                'effective_to' => $effectiveFrom,
            ]);

        $periodMonths = config('checkouts.default_period_months', 18);
        $expectedCheckoutAt = $effectiveFrom->copy()->addMonths($periodMonths);

        // Create new allocation
        \App\Models\RoomAllocation::query()->create([
            'tenant_id' => $tenantId,
            'student_id' => $student->id,
            'room_bed_id' => $bed->id,
            'hostel_id' => $hostelId,
            'effective_from' => $effectiveFrom,
            'is_active' => true,
            'note' => $data['note'] ?? null,
            'expected_checkout_at' => $expectedCheckoutAt,
            'checkout_status' => 'pending',
        ]);

        // Update bed status
        $bed->update([
            'status' => 'occupied',
            'occupied_at' => $effectiveFrom,
            'released_at' => null,
        ]);

        // Get student name from user
        $user = \App\Models\User::find($student->user_id);
        $studentName = $user ? $user->name : 'student';
        
        Notification::make()
            ->title('Room allocated')
            ->body("Room allocated successfully for {$studentName}.")
            ->success()
            ->send();
    }

    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        return Auth::check() && $user && $user->hasAnyRole(['Campus Manager', 'Super Admin', 'Rector']);
    }
}

