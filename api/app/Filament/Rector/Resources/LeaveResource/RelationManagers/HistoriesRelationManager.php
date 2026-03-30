<?php

namespace App\Filament\Rector\Resources\LeaveResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class HistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'histories';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('tenant_id')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('leave_id')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('acted_by')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('from_status')
                    ->maxLength(255),
                Forms\Components\TextInput::make('to_status')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('note')
                    ->maxLength(65535)
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('timeline_label')
                    ->maxLength(255),
                Forms\Components\Textarea::make('timeline_description')
                    ->maxLength(65535)
                    ->columnSpanFull(),
                Forms\Components\DateTimePicker::make('changed_at'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('timeline_label')
            ->columns([
                Tables\Columns\TextColumn::make('timeline_label')
                    ->label('Action'),
                Tables\Columns\TextColumn::make('timeline_description')
                    ->label('Description')
                    ->limit(50),
                Tables\Columns\TextColumn::make('from_status')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('to_status')
                    ->badge()
                    ->color(fn ($record) => match($record->to_status) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('actor.name')
                    ->label('By'),
                Tables\Columns\TextColumn::make('note')
                    ->label('Note')
                    ->limit(30)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        if (strlen($state) <= 30) {
                            return null;
                        }
                        return $state;
                    }),
                Tables\Columns\TextColumn::make('changed_at')
                    ->label('When')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('changed_at', 'desc')
            ->filters([
                //
            ])
            ->headerActions([
                // Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                // Tables\Actions\EditAction::make(),
                // Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                // Tables\Actions\BulkActionGroup::make([
                //     Tables\Actions\DeleteBulkAction::make(),
                // ]),
            ]);
    }
}
