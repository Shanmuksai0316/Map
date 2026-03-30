<?php

namespace App\Filament\Resources\Admin;

use App\Filament\Resources\Admin\AmenityResource\Pages;
use App\Models\Amenity;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AmenityResource extends Resource
{
    protected static ?string $model = Amenity::class;

    protected static ?string $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Amenities';

    protected static ?string $modelLabel = 'Amenity';

    protected static ?string $pluralModelLabel = 'Amenities';

    protected static ?string $slug = 'amenities';

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('Super Admin');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Amenity Details')
                    ->schema([
                        Forms\Components\TextInput::make('key')
                            ->label('Key')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->helperText('Unique identifier. Upper/lowercase input allowed; saved in lowercase (e.g., wifi, gym, laundry)')
                            ->regex('/^[A-Za-z0-9_]+$/')
                            ->dehydrateStateUsing(fn (?string $state): ?string => $state ? strtolower($state) : null)
                            ->placeholder('e.g., wifi, gym, laundry'),
                        
                        Forms\Components\TextInput::make('label')
                            ->label('Label')
                            ->required()
                            ->maxLength(100)
                            ->helperText('Display name for the amenity')
                            ->placeholder('e.g., WiFi, Gym, Laundry'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->label('Key')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),
                
                Tables\Columns\TextColumn::make('label')
                    ->label('Label')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('label', 'asc');
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
            'index' => Pages\ListAmenities::route('/'),
            'create' => Pages\CreateAmenity::route('/create'),
            'edit' => Pages\EditAmenity::route('/{record}/edit'),
        ];
    }
}
