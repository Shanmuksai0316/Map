<?php

namespace App\Filament\Resources\Admin;

use App\Events\StaffAssignmentChanged;
use App\Events\UserRoleChanged;
use App\Filament\Resources\Admin\StaffUserResource\Pages;
use App\Models\Hostel;
use App\Models\StaffUser;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class StaffUserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Staff Management';

    protected static ?string $navigationLabel = 'All Staff';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'staff-users';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('kind', '!=', 'student')
            ->where('archived', false)
            ->with(['tenantRelation', 'roles', 'staffHostels']);
    }

    public static function canAccess(): bool
    {
        return auth()->user()->hasRole('Super Admin');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Full Name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('phone')
                    ->label('Mobile Number')
                    ->tel()
                    ->required()
                    ->regex('/^[6-9]\d{9}$/')
                    ->unique(User::class, 'phone', ignoreRecord: true)
                    ->placeholder('9876543210')
                    ->helperText('10-digit Indian mobile number - used for OTP login')
                    ->maxLength(15),
                Forms\Components\Select::make('role_hint')
                    ->label('Role')
                    ->options(function () {
                        return Role::whereIn('name', [
                            'Campus Manager',
                            'Warden',
                            'HK Supervisor',
                            'RM Supervisor',
                            'Guard',
                            'Laundry Manager',
                            'Sports Manager',
                        ])->pluck('name', 'name');
                    })
                    ->required()
                    ->searchable()
                    ->afterStateUpdated(function ($state, $set) {
                        if ($state) {
                            $set('role', $state);
                        }
                    }),
            ]);
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
                    ->color('primary'),
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
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge()
                    ->color('info')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('staffHostels.name')
                    ->label('Assigned Hostel')
                    ->badge()
                    ->color('success')
                    ->placeholder('—')
                    ->limit(2),
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
                Tables\Filters\Filter::make('assigned')
                    ->label('Assigned Only')
                    ->query(fn (Builder $query) => $query->whereNotNull('tenant_id')),
                Tables\Filters\Filter::make('unassigned')
                    ->label('Unassigned Only')
                    ->query(fn (Builder $query) => $query->whereNull('tenant_id')),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('Actions'), // This will be the column header
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListStaffUsers::route('/'),
            'create' => Pages\CreateStaffUser::route('/create'),
            'edit' => Pages\EditStaffUser::route('/{record}/edit'),
        ];
    }
}