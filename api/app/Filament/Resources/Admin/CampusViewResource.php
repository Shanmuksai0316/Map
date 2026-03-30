<?php

namespace App\Filament\Resources\Admin;

use App\Filament\Resources\Admin\CampusViewResource\Pages;
use App\Models\Campus;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;

class CampusViewResource extends Resource
{
    protected static ?string $model = Campus::class;
    
    protected static ?string $navigationIcon = 'heroicon-o-building-library';
    
    protected static ?string $navigationLabel = 'Campuses';
    
    protected static ?string $pluralLabel = 'All Campuses';
    
    protected static ?string $navigationGroup = 'Operations';
    
    protected static ?int $navigationSort = 10;
    
    protected static ?string $slug = 'campuses';
    
    public static function canAccess(): bool
    {
        // Shows aggregated view of campuses across all tenants
        // Uses Campus model directly from single shared database
        return auth()->check() && auth()->user()->hasRole('Super Admin');
    }
    
    /**
     * Optimize query with eager loading and counts to prevent N+1 queries
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['tenant']) // Eager load tenant to avoid N+1 queries
            ->withCount('hostels') // Use withCount for performance
            ->orderBy('tenant_id'); // Group by tenant
    }
    
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Tenant')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('hostels_count')
                    ->label('Hostels')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label('Tenant')
                    ->relationship('tenant', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->defaultSort('tenant.name');
    }
    
    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Campus Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('tenant.name')
                            ->label('Tenant'),
                        Infolists\Components\TextEntry::make('code')
                            ->label('Code'),
                        Infolists\Components\TextEntry::make('name')
                            ->label('Name'),
                        Infolists\Components\KeyValueEntry::make('address')
                            ->label('Address'),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime(),
                    ]),
            ]);
    }
    
    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCampusViews::route('/'),
            'view' => Pages\ViewCampusView::route('/{record}'),
        ];
    }
    
    // Read-only - no create/edit/delete
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
