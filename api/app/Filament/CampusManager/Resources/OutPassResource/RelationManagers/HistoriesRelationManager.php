<?php

namespace App\Filament\CampusManager\Resources\OutPassResource\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class HistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'histories';

    protected static ?string $title = 'History';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('from_status')->label('From')->badge(),
                TextColumn::make('to_status')->label('To')->badge(),
                TextColumn::make('note')->wrap(),
                TextColumn::make('actor.name')->label('By'),
                TextColumn::make('changed_at')->dateTime('d M Y H:i')->label('When'),
            ])
            ->heading('Status History')
            ->defaultSort('changed_at', 'desc');
    }
}
