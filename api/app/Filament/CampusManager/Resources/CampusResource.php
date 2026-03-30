<?php

namespace App\Filament\CampusManager\Resources;

use App\Models\Campus;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\EditAction;

class CampusResource extends Resource
{
    protected static ?string $model = Campus::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    protected static ?string $navigationGroup = null;

    protected static ?string $modelLabel = 'Campus';

    protected static ?string $pluralModelLabel = 'Campuses';

    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        // Hide Campus management from Campus Manager left navigation,
        // while still allowing direct access if needed via route.
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Campus Information')
                    ->columns(2)
                    ->schema([
                        TextInput::make('code')
                            ->required()
                            ->maxLength(16)
                            ->unique(ignoreRecord: true),

                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('address')
                            ->label('Address')
                            ->columnSpanFull()
                            ->maxLength(500),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->label('Code')->searchable()->sortable(),
                TextColumn::make('name')->label('Name')->searchable()->sortable(),
                TextColumn::make('hostels_count')->label('Hostels')->counts('hostels'),
            ])
            ->defaultSort('name')
            ->actions([
                EditAction::make(),
            ]);
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
            'index' => \App\Filament\CampusManager\Resources\CampusResource\Pages\ListCampuses::route('/'),
            'create' => \App\Filament\CampusManager\Resources\CampusResource\Pages\CreateCampus::route('/create'),
            'edit' => \App\Filament\CampusManager\Resources\CampusResource\Pages\EditCampus::route('/{record}/edit'),
        ];
    }
}
