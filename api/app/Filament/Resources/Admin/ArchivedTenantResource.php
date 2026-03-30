<?php

namespace App\Filament\Resources\Admin;

use App\Enums\TenantStatus;
use App\Filament\Resources\Admin\ArchivedTenantResource\Pages;
use App\Models\Tenant;
use Filament\Forms\Form;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ArchivedTenantResource extends Resource
{
    protected static ?string $model = Tenant::class;

    protected static ?string $navigationIcon = 'heroicon-o-archive-box';

    protected static ?string $navigationGroup = 'Tenant Management';

    protected static ?string $navigationLabel = 'Archived Tenants';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'archived-tenants';

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('Super Admin');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('status', TenantStatus::ARCHIVED);
    }

    public static function form(Form $form): Form
    {
        return TenantResource::form($form);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                ViewEntry::make('wizard_summary')
                    ->view('filament.infolists.archived-tenant-wizard-summary'),
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
                    ->weight('medium')
                    ->color('gray'),
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
                TextColumn::make('archived_at')
                    ->label('Archived On')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created On')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                // No filters needed for archived view
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->icon('heroicon-o-eye')
                    ->tooltip('View archived tenant details'),
            ])
            ->bulkActions([])
            ->defaultSort('archived_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArchivedTenants::route('/'),
            'view' => Pages\ViewArchivedTenant::route('/{record}'),
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

