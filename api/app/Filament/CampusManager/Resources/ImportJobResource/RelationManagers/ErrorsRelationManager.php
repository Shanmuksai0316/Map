<?php

namespace App\Filament\CampusManager\Resources\ImportJobResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ErrorsRelationManager extends RelationManager
{
    protected static string $relationship = 'errors';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('row_number')->sortable(),
                TextColumn::make('code')->badge(),
                TextColumn::make('message')->wrap(),
                TextColumn::make('created_at')->since()->sortable(),
            ])
            ->paginationPageOptions([10, 25, 50]);
    }
}
