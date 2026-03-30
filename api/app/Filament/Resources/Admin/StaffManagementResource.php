<?php

namespace App\Filament\Resources\Admin;

use App\Filament\Resources\Admin\StaffManagementResource\Pages;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Hostel;
use App\Services\StaffAssignmentService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class StaffManagementResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Staff Management';

    protected static ?string $navigationLabel = 'All Staff';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'Staff Member';

    protected static ?string $pluralModelLabel = 'Staff Members';

    protected static ?string $slug = 'staff-managements';

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('Super Admin');
    }

    public static function getEloquentQuery(): Builder
    {
        // Show all non-archived staff users (MAP staff + college representatives),
        // regardless of tenant assignment. Filters can be used in the UI to narrow down.
        return parent::getEloquentQuery()
            ->where('kind', '!=', 'student')
            ->where('archived', false)
            ->with(['tenant', 'roles']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Staff Profile')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->label('Full Name'),
                        
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->label('Email Address'),
                        
                        Forms\Components\TextInput::make('phone')
                            ->tel()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(15)
                            ->label('Phone Number')
                            ->helperText('Will be used for login via OTP'),
                        
                        Forms\Components\Select::make('tenant_id')
                            ->label('Tenant')
                            ->options(fn () => Tenant::query()->where('status', 'active')->pluck('name', 'id')->toArray())
                            ->searchable()
                            ->preload()
                            ->live()
                            ->reactive()
                            ->helperText('Optional: Leave blank to add staff to Unassigned category. Can be assigned to a tenant later.')
                            ->placeholder('Select tenant (optional)')
                            ->disabled(fn ($record) => $record !== null), // Can't change tenant after creation via form
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('Additional Information')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('Internal Notes')
                            ->rows(3)
                            ->placeholder('Any additional information about this staff member...')
                            ->columnSpanFull(),
                    ])
                    ->collapsible()
                    ->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->label('Name'),
                
                Tables\Columns\TextColumn::make('phone')
                    ->searchable()
                    ->copyable()
                    ->label('Phone'),
                
                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Tenant')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color(fn ($state): string => $state ? 'info' : 'warning')
                    ->placeholder('Unassigned')
                    ->default('Unassigned'),
                
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge()
                    ->color('primary')
                    ->default('-'),
                
                Tables\Columns\TextColumn::make('active_assignment')
                    ->label('Assigned Hostel')
                    ->getStateUsing(function (User $record) {
                        try {
                            $assignment = DB::table('staff_assignments')
                                ->where('user_id', $record->id)
                                ->whereNull('revoked_at')
                                ->first();
                            
                            if (!$assignment) {
                                return 'Unassigned';
                            }
                            
                            $hostel = DB::table('hostels')
                                ->where('id', $assignment->hostel_id)
                                ->first();
                            
                            return $hostel?->name ?? 'Unknown Hostel';
                        } catch (\Exception $e) {
                            \Log::error("Error getting staff assignment: " . $e->getMessage());
                            return 'Error';
                        }
                    })
                    ->badge()
                    ->color(fn (string $state): string => match($state) {
                        'Unassigned' => 'warning',
                        'Error' => 'danger',
                        default => 'success'
                    }),
                
                Tables\Columns\TextColumn::make('assigned_since')
                    ->label('Since')
                    ->getStateUsing(function (User $record) {
                        try {
                            $assignment = DB::table('staff_assignments')
                                ->where('user_id', $record->id)
                                ->whereNull('revoked_at')
                                ->first();
                            
                            return $assignment?->assigned_at 
                                ? \Carbon\Carbon::parse($assignment->assigned_at)->format('d M Y')
                                : '-';
                        } catch (\Exception $e) {
                            return '-';
                        }
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label('Tenant')
                    ->relationship('tenant', 'name')
                    ->searchable()
                    ->preload(),
                
                Tables\Filters\SelectFilter::make('role')
                    ->label('Role')
                    ->options([
                        'Campus Manager' => 'Campus Manager',
                        'Warden' => 'Warden',
                        'Guard' => 'Guard',
                        'HK Supervisor' => 'HK Supervisor',
                        'RM Supervisor' => 'RM Supervisor',
                        'Laundry Manager' => 'Laundry Manager',
                        'Sports Manager' => 'Sports Manager',
                    ])
                    ->query(function (Builder $query, array $data) {
                        if (isset($data['value'])) {
                            $query->whereHas('roles', function ($q) use ($data) {
                                $q->where('name', $data['value']);
                            });
                        }
                    }),
                
                Tables\Filters\Filter::make('assignment_status')
                    ->label('Assignment Status')
                    ->form([
                        Forms\Components\Select::make('status')
                            ->options([
                                'assigned' => 'Assigned',
                                'unassigned' => 'Unassigned',
                            ])
                            ->placeholder('All'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (!isset($data['status'])) {
                            return $query;
                        }
                        
                        if ($data['status'] === 'assigned') {
                            return $query->whereHas('staffHostels');
                        } else {
                            return $query->whereDoesntHave('staffHostels');
                        }
                    }),
                
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->placeholder('All')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only')
                    ->queries(
                        true: fn (Builder $query) => $query->where('is_active', true),
                        false: fn (Builder $query) => $query->where('is_active', false),
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            ->actions([
                // Primary actions as individual buttons
                Tables\Actions\ViewAction::make()
                    ->icon('heroicon-o-eye')
                    ->tooltip('View staff details'),
                Tables\Actions\EditAction::make()
                    ->icon('heroicon-o-pencil')
                    ->tooltip('Edit staff'),
                
                // Secondary actions in dropdown
                Tables\Actions\ActionGroup::make([
                    // TOGGLE ACTIVE/INACTIVE ACTION
                    Tables\Actions\Action::make('toggle_active')
                        ->label(function (User $record) {
                            return $record->is_active ? 'Deactivate' : 'Activate';
                        })
                        ->icon(function (User $record) {
                            return $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle';
                        })
                        ->color(function (User $record) {
                            return $record->is_active ? 'danger' : 'success';
                        })
                        ->requiresConfirmation()
                        ->modalHeading(function (User $record) {
                            return $record->is_active ? 'Deactivate Staff Member' : 'Activate Staff Member';
                        })
                        ->modalDescription(function (User $record) {
                            return $record->is_active 
                                ? "This will deactivate {$record->name}. They will not be able to login until reactivated."
                                : "This will activate {$record->name}. They will be able to login again.";
                        })
                        ->action(function (User $record) {
                            $record->update(['is_active' => !$record->is_active]);
                            
                            Notification::make()
                                ->title($record->is_active ? 'Staff member activated' : 'Staff member deactivated')
                                ->body("{$record->name} has been " . ($record->is_active ? 'activated' : 'deactivated') . ".")
                                ->success()
                                ->send();
                        }),
                    
                    // ASSIGN/REASSIGN ACTION
                    Tables\Actions\Action::make('assign')
                        ->label(function (User $record) {
                            $service = app(StaffAssignmentService::class);
                            return $service->hasActiveAssignment($record) ? 'Reassign' : 'Assign';
                        })
                        ->icon('heroicon-o-arrow-path')
                        ->color('info')
                        ->form(function (User $record) {
                            $service = app(StaffAssignmentService::class);
                            $currentAssignment = $service->getActiveAssignment($record);
                            $currentHostel = $currentAssignment 
                                ? Hostel::find($currentAssignment->hostel_id)
                                : null;
                            
                            return [
                                Forms\Components\Placeholder::make('current_info')
                                    ->label('Current Assignment')
                                    ->content(function () use ($record, $currentHostel, $currentAssignment) {
                                        if (!$currentHostel) {
                                            return '⚠️ Not assigned to any hostel';
                                        }
                                        $role = $record->roles->first()?->name ?? 'No role';
                                        $tenant = Tenant::find($currentAssignment->tenant_id);
                                        return "✓ {$tenant->name} - {$currentHostel->name} as {$role}";
                                    })
                                    ->visible(fn () => $currentAssignment !== null),
                                
                                Forms\Components\Select::make('tenant_id')
                                    ->label('Tenant')
                                    ->options(fn () => Tenant::query()->where('status', 'active')->pluck('name', 'id'))
                                    ->default($record->tenant_id)
                                    ->required()
                                    ->live()
                                    ->reactive()
                                    ->searchable()
                                    ->preload()
                                    ->afterStateUpdated(fn (callable $set) => $set('hostel_id', null))
                                    ->helperText('⚠️ Can reassign staff to a different tenant (cross-tenant reassignment)'),
                                
                                Forms\Components\Select::make('role')
                                    ->label('Role')
                                    ->options([
                                        'Campus Manager' => 'Campus Manager',
                                        'Warden' => 'Warden',
                                        'Guard' => 'Guard',
                                        'HK Supervisor' => 'HK Supervisor',
                                        'RM Supervisor' => 'RM Supervisor',
                                        'Laundry Manager' => 'Laundry Manager',
                                        'Sports Manager' => 'Sports Manager',
                                    ])
                                    ->default($record->roles->first()?->name)
                                    ->required()
                                    ->searchable()
                                    ->live(),
                                
                                Forms\Components\Select::make('hostel_id')
                                    ->label('Assign to Hostel')
                                    ->options(function (callable $get) {
                                        $tenantId = $get('tenant_id');
                                        if (!$tenantId) {
                                            return [];
                                        }
                                        
                                        try {
                                            return Hostel::where('tenant_id', $tenantId)
                                                ->pluck('name', 'id')
                                                ->toArray();
                                        } catch (\Exception $e) {
                                            \Log::warning('StaffAssignment: Error loading hostels', [
                                                'tenant_id' => $tenantId,
                                                'error' => $e->getMessage()
                                            ]);
                                            return [];
                                        }
                                    })
                                    ->required()
                                    ->live()
                                    ->reactive()
                                    ->searchable()
                                    ->preload()
                                    ->disabled(fn (callable $get) => !filled($get('tenant_id')))
                                    ->placeholder(fn (callable $get) => filled($get('tenant_id')) ? 'Select hostel' : 'Select tenant first')
                                    ->helperText('Previous assignment will be automatically revoked'),
                                
                                Forms\Components\Textarea::make('notes')
                                    ->label('Assignment Notes')
                                    ->placeholder('Reason for assignment/reassignment, e.g., "New hire", "Promoted to Campus Manager", "Transferred from XYZ hostel"')
                                    ->rows(3),
                            ];
                        })
                        ->action(function (User $record, array $data) {
                            try {
                                $service = app(StaffAssignmentService::class);
                                $service->assignStaff($record, $data);
                                
                                $hostel = Hostel::find($data['hostel_id']);
                                $tenant = Tenant::find($data['tenant_id']);
                                
                                Notification::make()
                                    ->title('Staff Assigned Successfully')
                                    ->body("{$record->name} has been assigned to {$tenant->name} - {$hostel->name} as {$data['role']}")
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Assignment Failed')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->requiresConfirmation()
                        ->modalHeading(fn (User $record) => 'Assign/Reassign: ' . $record->name)
                        ->modalDescription('This will update the staff member\'s assignment. Previous assignment will be revoked automatically.')
                        ->modalSubmitActionLabel('Assign & Notify Staff'),
                    
                    // REVOKE ASSIGNMENT ACTION
                    Tables\Actions\Action::make('revoke')
                        ->label('Revoke Assignment')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(function (User $record) {
                            $service = app(StaffAssignmentService::class);
                            return $service->hasActiveAssignment($record);
                        })
                        ->form([
                            Forms\Components\Textarea::make('reason')
                                ->label('Reason for Revocation')
                                ->placeholder('e.g., On leave, Resigned, Suspended')
                                ->required()
                                ->rows(3),
                        ])
                        ->action(function (User $record, array $data) {
                            try {
                                $service = app(StaffAssignmentService::class);
                                $service->revokeAssignment($record, $data['reason']);
                                
                                Notification::make()
                                    ->title('Assignment Revoked')
                                    ->body("{$record->name}'s hostel assignment has been revoked. They can no longer access mobile app.")
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Revocation Failed')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->requiresConfirmation()
                        ->modalHeading('Revoke Assignment')
                        ->modalDescription('This will remove the staff member from their assigned hostel. They will not be able to access the mobile app until reassigned.'),

                    Tables\Actions\Action::make('archive')
                        ->label('Archive Staff')
                        ->icon('heroicon-o-archive-box')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->form([
                            Forms\Components\Textarea::make('reason')
                                ->label('Archive Reason')
                                ->required()
                                ->rows(3),
                        ])
                        ->action(function (User $record, array $data) {
                            DB::transaction(function () use ($record, $data) {
                                DB::table('staff_assignments')
                                    ->where('user_id', $record->id)
                                    ->whereNull('revoked_at')
                                    ->update([
                                        'revoked_at' => now(),
                                        'revocation_reason' => 'Staff archived: ' . $data['reason'],
                                        'updated_at' => now(),
                                    ]);

                                $record->update([
                                    'archived' => true,
                                    'archived_at' => now(),
                                    'archived_reason' => $data['reason'],
                                    'is_active' => false,
                                ]);
                            });

                            Notification::make()
                                ->title('Staff archived')
                                ->body("{$record->name} moved to Archived Staff.")
                                ->success()
                                ->send();
                        }),
                    
                    // VIEW HISTORY ACTION
                    Tables\Actions\Action::make('history')
                        ->label('Assignment History')
                        ->icon('heroicon-o-clock')
                        ->color('secondary')
                        ->modalContent(function (User $record) {
                            $service = app(StaffAssignmentService::class);
                            $history = $service->getAssignmentHistory($record);
                            
                            if ($history->isEmpty()) {
                                return view('filament.pages.empty-state', [
                                    'message' => 'No assignment history found'
                                ]);
                            }
                            
                            return view('filament.pages.staff-assignment-history', [
                                'history' => $history,
                                'staff' => $record,
                            ]);
                        })
                        ->modalHeading(fn (User $record) => 'Assignment History: ' . $record->name)
                        ->slideOver(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    // No bulk delete - too dangerous for staff records
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s'); // Auto-refresh every 30 seconds
    }

    public static function getRelations(): array
    {
        return [
            // Can add relation managers here if needed
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStaffManagement::route('/'),
            'create' => Pages\CreateStaffManagement::route('/create'),
            'view' => Pages\ViewStaffManagement::route('/{record}'),
            'edit' => Pages\EditStaffManagement::route('/{record}/edit'),
        ];
    }
}
