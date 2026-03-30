<?php

namespace App\Filament\Resources\Admin;

use App\Filament\Resources\Admin\UnassignedStaffResource\Pages;
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

class UnassignedStaffResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-minus';

    protected static ?string $navigationGroup = 'Staff Management';

    protected static ?string $navigationLabel = 'Unassigned Staff';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'unassigned-staff';

    protected static ?string $modelLabel = 'Unassigned Staff';

    protected static ?string $pluralModelLabel = 'Unassigned Staff';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('kind', '!=', 'student')
            ->where('archived', false)
            ->whereNull('tenant_id')
            ->with(['roles']);
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('Super Admin');
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
                    ->color('warning')
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
                    ->placeholder('Not Assigned')
                    ->color('gray'),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge()
                    ->color('gray')
                    ->placeholder('Not Assigned'),
                Tables\Columns\TextColumn::make('staffHostels.name')
                    ->label('Assigned Hostel')
                    ->placeholder('Not Assigned')
                    ->color('gray'),
            ])
            ->filters([])
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
                        return [
                            Forms\Components\Select::make('tenant_id')
                                ->label('Tenant')
                                ->options(fn () => Tenant::query()->where('status', 'active')->pluck('name', 'id'))
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
            'index' => Pages\ListUnassignedStaff::route('/'),
            'create' => Pages\CreateUnassignedStaff::route('/create'),
            'view' => Pages\ViewUnassignedStaff::route('/{record}'),
            'edit' => Pages\EditUnassignedStaff::route('/{record}/edit'),
        ];
    }

    protected static function staffRoleOptions(): array
    {
        return collect(User::mapStaffRoles())->mapWithKeys(fn (string $role): array => [$role => $role])->toArray();
    }
}
