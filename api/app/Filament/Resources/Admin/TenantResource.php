<?php

namespace App\Filament\Resources\Admin;

use App\Enums\TenantStatus;
use App\Filament\Resources\Admin\TenantResource\Pages;
use App\Filament\Resources\Admin\TenantResource\RelationManagers\HostelsRelationManager;
use App\Models\Tenant;
use App\Services\AuditService;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Tenant Management';
    
    protected static ?string $navigationLabel = 'All Tenants';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'tenants';
    
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Tenant Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('code')
                                    ->label('Code')
                                    ->required()
                                    ->maxLength(24)
                                    ->regex('/^MAP-[A-Z0-9-]{2,20}$/')
                                    ->afterStateUpdated(fn ($state, $set) => $state ? $set('code', strtoupper($state)) : null)
                                    ->disabled(fn ($record) => $record && $record->status === TenantStatus::ACTIVE)
                                    ->dehydrated(fn ($record) => !$record || $record->status !== TenantStatus::ACTIVE)
                                    ->helperText('Must start with MAP-, uppercase letters/numbers only. Locked after activation.'),
                                Forms\Components\TextInput::make('name')
                                    ->label('Name')
                                    ->required()
                                    ->maxLength(120)
                                    ->disabled(fn ($record) => $record && $record->status === TenantStatus::ACTIVE)
                                    ->dehydrated(fn ($record) => !$record || $record->status !== TenantStatus::ACTIVE)
                                    ->helperText(fn ($record) => $record && $record->status === TenantStatus::ACTIVE 
                                        ? 'Name cannot be changed after activation' 
                                        : null),
                            ]),
                    ]),
                Section::make('Add-ons')
                    ->schema([
                        Forms\Components\Placeholder::make('addons_locked_notice')
                            ->content('All modules are enabled for every tenant in v1 (Security, Sports, Laundry).')
                            ->extraAttributes(['class' => 'text-sm text-gray-600']),
                    ]),
                Section::make('Branding')
                    ->schema([
                        Forms\Components\FileUpload::make('settings.branding.logo_path')
                            ->label('Logo')
                            ->image()
                            ->disk('public_central')
                            ->directory('branding/logos')
                            ->maxSize(2048),
                    ]),
                Section::make('Contact & Address')
                    ->schema([
                        Grid::make(2)->schema([
                            Forms\Components\TextInput::make('settings.contact.name')->label('Contact Person'),
                            Forms\Components\TextInput::make('settings.contact.phone')->label('Mobile'),
                        ]),
                        Forms\Components\TextInput::make('settings.contact.email')->label('Email'),
                        Grid::make(2)->schema([
                            Forms\Components\TextInput::make('settings.address.street')->label('Street/Building'),
                            Forms\Components\TextInput::make('settings.address.city')->label('City'),
                        ]),
                        Grid::make(2)->schema([
                            Forms\Components\TextInput::make('settings.address.state')->label('State'),
                            Forms\Components\TextInput::make('settings.address.pincode')->numeric()->length(6)->label('Pincode'),
                        ]),
                        Forms\Components\TextInput::make('settings.address.country')->default('India')->label('Country'),
                    ]),
                Section::make('Tenant Status & Notes')
                    ->description('Lifecycle status and internal notes')
                    ->schema([
                        Grid::make(2)->schema([
                            Forms\Components\Select::make('payment_mode')
                                ->label('Payment Mode')
                                ->options([
                                    'offline' => 'Offline (Bank Transfer/Cheque)',
                                ])
                                ->default('offline'),
                            Forms\Components\Select::make('status')
                                ->label('Tenant Status')
                                ->options([
                                    'provisioning' => 'Provisioning',
                                    'active' => 'Active',
                                    'archived' => 'Archived',
                                ])
                                ->default('active')
                                ->required()
                                ->disabled() // Status changes via lifecycle actions only
                                ->helperText('Use lifecycle actions (Archive) to change status'),
                        ]),
                        Forms\Components\Textarea::make('payment_notes')
                            ->label('Internal Notes')
                            ->helperText('Optional notes for admin records.')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
                Section::make('Settings')
                    ->description('Additional key-value settings. Structural keys (branding, contact, address) are edited in the sections above.')
                    ->schema([
                        Forms\Components\KeyValue::make('settings.extra')
                            ->label('Feature Flags / Extra Settings')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('medium')
                    ->color('primary'),
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('hostels_count')
                    ->label('Hostels')
                    ->counts('hostels')
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('students_count')
                    ->label('Students')
                    ->getStateUsing(function (Tenant $record): int {
                        return \App\Models\Student::where('tenant_id', $record->id)->count();
                    })
                    ->alignCenter(),
                TextColumn::make('staff_count')
                    ->label('Staff')
                    ->getStateUsing(function (Tenant $record): int {
                        return \App\Models\User::where('tenant_id', $record->id)
                            ->where('kind', '!=', 'student')
                            ->count();
                    })
                    ->alignCenter(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (Tenant $record): string => $record->status->color())
                    ->formatStateUsing(fn (TenantStatus $state): string => $state->label())
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created On')
                    ->date('d M Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\Filter::make('all')
                    ->label('All Tenants')
                    ->query(fn (Builder $query): Builder => $query)
                    ->default(),
                Tables\Filters\Filter::make('active')
                    ->label('Active Tenants')
                    ->query(fn (Builder $query): Builder => $query->active()),
                Tables\Filters\Filter::make('archived')
                    ->label('Archived Tenants')
                    ->query(fn (Builder $query): Builder => $query->archived()),
            ])
            ->headerActions([
                Tables\Actions\Action::make('exportTenants')
                    ->label('Export')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn () => static::streamTenantCsv()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label('Actions'), // This will be the column header
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('resumeOnboarding')
                    ->label('Resume Onboarding')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (Tenant $record): bool => $record->status === TenantStatus::PROVISIONING)
                    ->url(fn (Tenant $record): string => route('filament.admin.pages.tenant-onboarding-wizard', [
                        'tenant_id' => $record->id,
                    ])),
                Tables\Actions\Action::make('archive')
                    ->label('Archive')
                    ->icon('heroicon-o-archive-box')
                    ->color('secondary')
                    ->visible(fn (Tenant $record): bool => $record->status === TenantStatus::ACTIVE)
                    ->requiresConfirmation()
                    ->modalHeading('Archive Tenant')
                    ->modalDescription('This tenant will become read-only and move to Archived Tenants.')
                    ->action(function (Tenant $record): void {
                        $record->archive();

                        Notification::make()
                            ->title('Tenant archived')
                            ->body("Tenant '{$record->name}' moved to archived list.")
                            ->success()
                            ->send();
                    }),
            ])
            // Commented out old actions
            /*
            ->actions([
                // Primary actions as individual buttons
                Tables\Actions\ViewAction::make()
                    ->icon('heroicon-o-eye')
                    ->tooltip('View tenant details')
                    ->after(function (Tenant $record) {
                        app(AuditService::class)->log('tenant_view', $record);
                    }),
                Tables\Actions\EditAction::make()
                    ->icon('heroicon-o-pencil')
                    ->tooltip('Edit tenant'),
                
                // Secondary actions in dropdown
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('archive')
                        ->label('Archive')
                        ->icon('heroicon-o-archive-box')
                        ->color('secondary')
                        ->visible(fn (Tenant $record): bool => 
                            $record->status === TenantStatus::ACTIVE
                        )
                        ->requiresConfirmation()
                        ->modalHeading('Archive Tenant')
                        ->modalDescription('This will mark the tenant as archived (read-only, non-reactivable). Archived tenants remain for records/compliance only.')
                        ->action(function (Tenant $record): void {
                            $record->archive();
                            
                            Notification::make()
                                ->title('Tenant archived successfully')
                                ->body("Tenant '{$record->name}' has been archived. It cannot be reactivated.")
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('rollback')
                        ->label('Rollback (24h)')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('warning')
                        ->visible(fn (Tenant $record): bool =>
                            $record->status === TenantStatus::ACTIVE
                        )
                        ->form([
                            Forms\Components\Textarea::make('reason')
                                ->label('Reason')
                                ->rows(2)
                                ->required()
                                ->default('Rollback within 24h window'),
                            Forms\Components\TextInput::make('idempotency_key')
                                ->label('Idempotency-Key')
                                ->maxLength(64)
                                ->helperText('Optional: prevents duplicate rollback requests within 24h.'),
                        ])
                        ->requiresConfirmation()
                        ->modalHeading('Rollback tenant to provisioning')
                        ->modalDescription('Rollback is allowed only within 24h of activation. Structural edits remain locked until re-activation.')
                        ->action(function (Tenant $record, array $data) {
                            $admin = auth()->user();
                            $idempotency = app(\App\Services\IdempotencyService::class);

                            // Simple 24h guard using updated_at as proxy for activation time.
                            if ($record->updated_at->lt(now()->subHours(24))) {
                                Notification::make()
                                    ->title('Rollback window expired')
                                    ->body('Rollback is allowed only within 24 hours of activation.')
                                    ->warning()
                                    ->send();
                                return;
                            }

                            try {
                                $idempotency->assertUnique(
                                    action: 'tenant_rollback',
                                    key: $data['idempotency_key'] ?? null,
                                    userId: $admin?->id,
                                    tenantId: (string) $record->id,
                                    fingerprint: ['tenant_id' => $record->id]
                                );
                            } catch (\RuntimeException $e) {
                                Notification::make()
                                    ->title('Duplicate rollback')
                                    ->body($e->getMessage())
                                    ->warning()
                                    ->send();
                                return;
                            }

                            $record->update([
                                'status' => TenantStatus::PROVISIONING,
                            ]);

                            \Illuminate\Support\Facades\DB::table('tenant_impersonation_logs')
                                ->insert([
                                    'super_admin_id' => (string) $admin?->id,
                                    'tenant_id' => $record->id,
                                    'impersonated_user_id' => null,
                                    'started_at' => null,
                                    'ended_at' => now(),
                                    'reason' => 'Tenant rollback: ' . ($data['reason'] ?? 'N/A'),
                                    'ip_address' => request()->ip(),
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ]);

                            Notification::make()
                                ->title('Tenant rolled back')
                                ->body("{$record->name} is now in provisioning. Structural edits remain locked until re-activation.")
                                ->success()
                                ->send();
                        }),
                    
                    Tables\Actions\Action::make('impersonate')
                        ->label('Impersonate')
                        ->icon('heroicon-o-user-circle')
                        ->color('info')
                        ->visible(fn (Tenant $record): bool => 
                            $record->status === TenantStatus::ACTIVE
                        )
                        ->form([
                            Forms\Components\Textarea::make('reason')
                                ->label('Reason')
                                ->required()
                                ->rows(2)
                                ->default('Support/Debugging'),
                        ])
                        ->action(function (Tenant $record, array $data) {
                            $admin = auth()->user();
                            if (!$admin?->hasRole('Super Admin')) {
                                Notification::make()
                                    ->title('Access denied')
                                    ->body('Only Super Admin can impersonate tenants.')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            if (session('impersonating_from')) {
                                Notification::make()
                                    ->title('Already impersonating')
                                    ->body('Stop current impersonation before starting a new one.')
                                    ->warning()
                                    ->send();
                                return;
                            }

                            $tenantAdmin = \App\Models\User::where('tenant_id', $record->id)
                                ->whereHas('roles', fn($q) => $q->whereIn('name', ['Rector', 'Campus Manager']))
                                ->first();

                            if (!$tenantAdmin) {
                                Notification::make()
                                    ->title('No admin found')
                                    ->body('Assign a Rector or Campus Manager before impersonating.')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            \Illuminate\Support\Facades\DB::table('tenant_impersonation_logs')->insert([
                                'super_admin_id' => (string) $admin->id,
                                'tenant_id' => $record->id,
                                'impersonated_user_id' => (string) $tenantAdmin->id,
                                'started_at' => now(),
                                'ip_address' => request()->ip(),
                                'reason' => $data['reason'] ?? 'Support/Debugging',
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);

                            session([
                                'impersonating_from' => $admin->id,
                                'impersonation_started_at' => now()->toIso8601String(),
                                'impersonated_tenant_name' => $record->name,
                            ]);

                            app(AuditService::class)->log('tenant_impersonation_start', $record, [
                                'reason' => $data['reason'] ?? null,
                            ]);

                            auth()->login($tenantAdmin);

                            return redirect()
                                ->route('filament.campus-manager.pages.dashboard')
                                ->with('warning', 'IMPERSONATION MODE: You are viewing as ' . $tenantAdmin->name . '. Click "Stop Impersonation" to return to Super Admin.');
                        }),
                ]),
            ])
            */
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete tenants')
                        ->modalDescription('This will soft delete the selected tenants. They can be restored later.'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    protected static function streamTenantCsv(): StreamedResponse
    {
        $rows = Tenant::query()
            ->withCount(['hostels'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Tenant $tenant): array {
                return [
                    'Code' => $tenant->code,
                    'Name' => $tenant->name,
                    'Status' => $tenant->status->value ?? (string) $tenant->status,
                    'Hostels' => (int) ($tenant->hostels_count ?? 0),
                    'Students' => \App\Models\Student::where('tenant_id', $tenant->id)->count(),
                    'Staff' => \App\Models\User::where('tenant_id', $tenant->id)
                        ->where('kind', '!=', 'student')
                        ->count(),
                    'Created On' => optional($tenant->created_at)->format('Y-m-d H:i:s') ?? '',
                ];
            });

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Code', 'Name', 'Status', 'Hostels', 'Students', 'Staff', 'Created On']);
            foreach ($rows as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
        }, 'tenants.csv');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                \Filament\Infolists\Components\Section::make('Tenant Details')
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('code')->label('Code'),
                        \Filament\Infolists\Components\TextEntry::make('name')->label('Name'),
                    ])
                    ->columns(2),
                \Filament\Infolists\Components\Section::make('Branding')
                    ->schema([
                        \Filament\Infolists\Components\ImageEntry::make('settings.branding.logo_path')
                            ->label('Logo')
                            ->disk('public')
                            ->visibility('public')
                            ->getStateUsing(function ($record) {
                                $path = data_get($record?->settings, 'branding.logo_path');
                                if (is_array($path)) {
                                    $path = $path[0] ?? $path['path'] ?? null;
                                }
                                return $path;
                            })
                            ->defaultImageUrl(fn () => asset('images/map-logo.svg'))
                            ->height(80),
                    ]),
                \Filament\Infolists\Components\Section::make('Contact & Address')
                    ->schema([
                        \Filament\Infolists\Components\TextEntry::make('settings.contact.name')->label('Contact Person'),
                        \Filament\Infolists\Components\TextEntry::make('settings.contact.email')->label('Email'),
                        \Filament\Infolists\Components\TextEntry::make('settings.contact.phone')->label('Phone'),
                        \Filament\Infolists\Components\TextEntry::make('settings.address.street')->label('Street'),
                        \Filament\Infolists\Components\TextEntry::make('settings.address.city')->label('City'),
                        \Filament\Infolists\Components\TextEntry::make('settings.address.state')->label('State'),
                        \Filament\Infolists\Components\TextEntry::make('settings.address.pincode')->label('Pincode'),
                        \Filament\Infolists\Components\TextEntry::make('settings.address.country')->label('Country'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            HostelsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenants::route('/'),
            // 'create' => Pages\CreateTenant::route('/create'), // REMOVED: Use Onboarding Wizard instead
            'view' => Pages\ViewTenant::route('/{record}'),
            'edit' => Pages\EditTenant::route('/{record}/edit'),
        ];
    }
}
