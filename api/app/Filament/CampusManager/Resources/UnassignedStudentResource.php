<?php

namespace App\Filament\CampusManager\Resources;

use App\Filament\CampusManager\Resources\UnassignedStudentResource\Pages;
use App\Http\Middleware\SetPostgresSessionTenant;
use App\Models\Student;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class UnassignedStudentResource extends Resource
{
    protected static ?string $model = Student::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-minus';

    protected static ?string $navigationGroup = 'Student Management';

    protected static ?string $navigationLabel = 'Unassigned Students';

    protected static ?string $modelLabel = 'Unassigned Student';

    protected static ?string $pluralModelLabel = 'Unassigned Students';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'unassigned-students';

    public static function form(Form $form): Form
    {
        return StudentResource::form($form);
    }

    public static function table(Table $table): Table
    {
        try {
            $tenantId = static::resolveTenantId();
            
            if (!$tenantId) {
                \Log::warning('UnassignedStudentResource: No tenant ID found', [
                    'user_id' => auth()->id(),
                ]);
            }
            
            return $table
                ->modifyQueryUsing(function (Builder $query) use ($tenantId) {
                    if ($tenantId) {
                        SetPostgresSessionTenant::setTenantSessionVariable($tenantId);
                        $query->where('students.tenant_id', $tenantId);
                    }

                    // Apply hostel switcher scope from session
                    $activeHostelId = session('active_hostel_id');
                    if ($activeHostelId) {
                        $query->where('students.hostel_id', $activeHostelId);
                    }

                    // Only unassigned students (no active room allocation)
                    return $query->with(['hostel', 'roomAllocations'])
                        ->whereDoesntHave('roomAllocations', function ($q) use ($tenantId) {
                            $q->where('is_active', true);
                            if ($tenantId) {
                                $q->where('tenant_id', $tenantId);
                            }
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
                            \Log::warning('UnassignedStudentResource: Failed to load user', [
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
                            \Log::warning('UnassignedStudentResource: Failed to load user phone', [
                                'user_id' => $record->user_id,
                                'error' => $e->getMessage(),
                            ]);
                            return $record->mobile_number ?? '—';
                        }
                    }),

                Tables\Columns\TextColumn::make('hostel_name')
                    ->label('Hostel')
                    ->getStateUsing(fn () => '—'),

                Tables\Columns\TextColumn::make('allocated_room')
                    ->label('Room')
                    ->getStateUsing(fn () => '—'),

                Tables\Columns\TextColumn::make('allocation_status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn () => 'Unassigned')
                    ->color('warning'),
            ])
            ->filters([
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
            ->bulkActions([
                Tables\Actions\BulkAction::make('activateStudents')
                    ->label('Activate Selected')
                    ->icon('heroicon-o-user-plus')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Activate Students')
                    ->modalDescription('This will activate the selected students and send Welcome SMS with OTP login credentials. Students will be moved to the active pool.')
                    ->deselectRecordsAfterCompletion()
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records): void {
                        $activated = 0;
                        $failed = 0;

                        foreach ($records as $student) {
                            try {
                                // Activate the user account
                                if ($student->user) {
                                    $student->user->update([
                                        'is_active' => true,
                                        'status' => 'Active',
                                    ]);
                                }

                                // Fire activation event (sends welcome SMS)
                                event(new \App\Events\StudentActivated($student));
                                $activated++;
                            } catch (\Exception $e) {
                                \Illuminate\Support\Facades\Log::error('Failed to activate student', [
                                    'student_id' => $student->id,
                                    'error' => $e->getMessage(),
                                ]);
                                $failed++;
                            }
                        }

                        \Filament\Notifications\Notification::make()
                            ->success()
                            ->title('Students Activated')
                            ->body("Activated {$activated} student(s)." . ($failed > 0 ? " {$failed} failed." : ''))
                            ->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
        } catch (\Exception $e) {
            \Log::error('UnassignedStudentResource: Error in table()', [
                'error' => $e->getMessage(),
                'trace' => substr($e->getTraceAsString(), 0, 500),
            ]);
            
            // Return a safe table query that won't crash
            return $table
                ->query(Student::query()->whereRaw('1 = 0')) // Empty query
                ->columns([])
                ->emptyStateHeading('Error loading unassigned students')
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
            'index' => Pages\ListUnassignedStudents::route('/'),
            'view' => Pages\ViewUnassignedStudent::route('/{record}'),
            'edit' => Pages\EditUnassignedStudent::route('/{record}/edit'),
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
            \Log::error('UnassignedStudentResource: canAccess error', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}

