<?php

namespace App\Filament\Resources\Admin;

use App\Filament\Resources\Admin\ArchivedStaffResource\Pages;
use App\Models\User;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ArchivedStaffResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'Staff Management';

    protected static ?string $navigationLabel = 'Archived Staff';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'archived-staff';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('kind', '!=', 'student')
            ->where('archived', true)
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
                    ->color('gray'),
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('gray'),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable(),
                Tables\Columns\TextColumn::make('tenantRelation.name')
                    ->label('Last Tenant')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Last Role')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('archived_at')
                    ->label('Archived On')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('archived_reason')
                    ->label('Reason')
                    ->limit(30)
                    ->tooltip(fn (User $record) => $record->archived_reason),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label('Last Tenant')
                    ->relationship('tenantRelation', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('archived_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArchivedStaff::route('/'),
            'view' => Pages\ViewArchivedStaff::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}

