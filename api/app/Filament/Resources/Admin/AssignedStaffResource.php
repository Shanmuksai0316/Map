<?php

namespace App\Filament\Resources\Admin;

use App\Filament\Resources\Admin\AssignedStaffResource\Pages;
use App\Models\Hostel;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StaffAssignmentService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssignedStaffResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-plus';

    protected static ?string $navigationGroup = 'Staff Management';

    protected static ?string $navigationLabel = 'Assigned Staff';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'assigned-staff';

    protected static ?string $modelLabel = 'Assigned Staff';

    protected static ?string $pluralModelLabel = 'Assigned Staff';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('kind', '!=', 'student')
            ->where('archived', false)
            ->whereNotNull('tenant_id')
            ->with(['tenantRelation', 'roles', 'staffHostels']);
    }

    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('Super Admin');
    }

    public static function form(Form $form): Form
    {
        return StaffUserResource::form($form);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('employee_id')
                    ->label('Employee ID')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->color('primary')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->copyable(),
                Tables\Columns\TextColumn::make('tenantRelation.name')
                    ->label('Tenant')
                    ->sortable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('staffHostels.name')
                    ->label('Assigned Hostel')
                    ->badge()
                    ->color('success')
                    ->limit(2)
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label('Tenant')
                    ->relationship('tenantRelation', 'name'),
                Tables\Filters\SelectFilter::make('role')
                    ->label('Role')
                    ->options(function () {
                        return Role::whereIn('name', User::mapStaffRoles())->pluck('name', 'name');
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) {
                            return $query;
                        }
                        return $query->whereHas('roles', fn ($q) => $q->where('name', $data['value']));
                    }),
            ])
            ->headerActions([
                Tables\Actions\Action::make('exportStaff')
                    ->label('Export')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn () => static::streamAssignedStaffCsv()),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggle_active')
                    ->label(fn (User $record): string => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn (User $record): string => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (User $record): string => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        $record->update(['is_active' => !$record->is_active]);
                        Notification::make()
                            ->title($record->is_active ? 'Staff member activated' : 'Staff member deactivated')
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('assign')
                    ->label(function (User $record): string {
                        $service = app(StaffAssignmentService::class);
                        return $service->hasActiveAssignment($record) ? 'Reassign' : 'Assign';
                    })
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->form(function (User $record): array {
                        $service = app(StaffAssignmentService::class);
                        $currentAssignment = $service->getActiveAssignment($record);

                        return [
                            Forms\Components\Select::make('tenant_id')
                                ->label('Tenant')
                                ->options(fn () => Tenant::query()->where('status', 'active')->pluck('name', 'id'))
                                ->default($record->tenant_id)
                                ->required()
                                ->live()
                                ->afterStateUpdated(fn (callable $set) => $set('hostel_id', null))
                                ->searchable()
                                ->preload(),
                            Forms\Components\Select::make('role')
                                ->label('Role')
                                ->options(static::staffRoleOptions())
                                ->default($record->roles->first()?->name)
                                ->required()
                                ->searchable(),
                            Forms\Components\Select::make('hostel_id')
                                ->label('Hostel')
                                ->options(function (callable $get): array {
                                    $tenantId = $get('tenant_id');
                                    if (!$tenantId) {
                                        return [];
                                    }
                                    return Hostel::where('tenant_id', $tenantId)->pluck('name', 'id')->toArray();
                                })
                                ->required()
                                ->searchable()
                                ->preload()
                                ->disabled(fn (callable $get): bool => !filled($get('tenant_id'))),
                            Forms\Components\Textarea::make('notes')
                                ->label('Assignment Notes')
                                ->rows(3),
                            Forms\Components\Placeholder::make('current_assignment')
                                ->label('Current Assignment')
                                ->content(function () use ($currentAssignment): string {
                                    if (!$currentAssignment) {
                                        return 'No active assignment';
                                    }
                                    $tenant = Tenant::find($currentAssignment->tenant_id);
                                    $hostel = Hostel::find($currentAssignment->hostel_id);
                                    return ($tenant?->name ?? 'Unknown tenant') . ' - ' . ($hostel?->name ?? 'Unknown hostel');
                                }),
                        ];
                    })
                    ->action(function (User $record, array $data): void {
                        try {
                            app(StaffAssignmentService::class)->assignStaff($record, $data);
                            Notification::make()->title('Staff assigned successfully')->success()->send();
                        } catch (\Exception $e) {
                            Notification::make()->title('Assignment failed')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('revoke')
                    ->label('Revoke Assignment')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(function (User $record): bool {
                        return app(StaffAssignmentService::class)->hasActiveAssignment($record);
                    })
                    ->form([
                        Forms\Components\Textarea::make('reason')
                            ->label('Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (User $record, array $data): void {
                        try {
                            app(StaffAssignmentService::class)->revokeAssignment($record, $data['reason']);
                            Notification::make()->title('Assignment revoked')->success()->send();
                        } catch (\Exception $e) {
                            Notification::make()->title('Revocation failed')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Tables\Actions\Action::make('history')
                    ->label('Assignment History')
                    ->icon('heroicon-o-clock')
                    ->color('gray')
                    ->modalHeading(fn (User $record): string => 'Assignment History: ' . $record->name)
                    ->modalContent(function (User $record) {
                        $history = app(StaffAssignmentService::class)->getAssignmentHistory($record);
                        if ($history->isEmpty()) {
                            return view('filament.pages.empty-state', ['message' => 'No assignment history found']);
                        }
                        return view('filament.pages.staff-assignment-history', [
                            'history' => $history,
                            'staff' => $record,
                        ]);
                    })
                    ->slideOver(),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAssignedStaff::route('/'),
            'view' => Pages\ViewAssignedStaff::route('/{record}'),
            'edit' => Pages\EditAssignedStaff::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    protected static function streamAssignedStaffCsv(): StreamedResponse
    {
        $rows = static::getEloquentQuery()
            ->get()
            ->map(function (User $user): array {
                return [
                    'Employee ID' => $user->employee_id ?? '',
                    'Name' => $user->name ?? '',
                    'Phone' => $user->phone ?? '',
                    'Tenant' => $user->tenantRelation?->name ?? '',
                    'Roles' => $user->roles->pluck('name')->implode(', '),
                    'Assigned Hostels' => $user->staffHostels->pluck('name')->implode(', '),
                ];
            });

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Employee ID', 'Name', 'Phone', 'Tenant', 'Roles', 'Assigned Hostels']);
            foreach ($rows as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
        }, 'staff.csv');
    }

    protected static function staffRoleOptions(): array
    {
        return collect(User::mapStaffRoles())->mapWithKeys(fn (string $role): array => [$role => $role])->toArray();
    }
}
