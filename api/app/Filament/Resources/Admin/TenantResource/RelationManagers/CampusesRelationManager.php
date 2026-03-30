<?php

namespace App\Filament\Resources\Admin\TenantResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class CampusesRelationManager extends RelationManager
{
    protected static string $relationship = 'campuses';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Campus Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Forms\Components\TextInput::make('code')
                                    ->label('Code')
                                    ->required()
                                    ->maxLength(16),
                                Forms\Components\TextInput::make('name')
                                    ->label('Name')
                                    ->required()
                                    ->maxLength(120),
                            ]),
                        KeyValue::make('address')->label('Address')->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('code')->label('Code')->searchable()->sortable(),
                TextColumn::make('name')->label('Name')->searchable()->sortable(),
                TextColumn::make('created_at')->label('Created')->dateTime('d M Y H:i')->sortable(),
            ])
            ->headerActions([
                Tables\Actions\Action::make('addCampus')
                    ->label('New campus')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->visible(fn (): bool => (bool) (
                        $this->getOwnerRecord()->campuses()->count() === 0
                        && (Auth::user()?->hasRole('Super Admin') || Auth::user()?->tenant_id)
                    ))
                    ->modalHeading('Create Campus')
                    ->form([
                        Forms\Components\TextInput::make('code')
                            ->label('Code')
                            ->required()
                            ->maxLength(16),
                        Forms\Components\TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(120),
                        KeyValue::make('address')
                            ->label('Address')
                            ->default([]),
                    ])
                    ->action(function (array $data): void {
                        $this->getOwnerRecord()->campuses()->create([
                            'tenant_id' => $this->getOwnerRecord()->id,
                            'code' => $data['code'],
                            'name' => $data['name'],
                            'address' => $data['address'] ?? [],
                        ]);
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('editCampus')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil-square')
                    ->fillForm(fn ($record): array => [
                        'code' => $record->code,
                        'name' => $record->name,
                        'address' => $record->address ?? [],
                    ])
                    ->form([
                        Forms\Components\TextInput::make('code')
                            ->label('Code')
                            ->required()
                            ->maxLength(16),
                        Forms\Components\TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(120),
                        KeyValue::make('address')
                            ->label('Address')
                            ->default([]),
                    ])
                    ->action(function ($record, array $data): void {
                        $record->update([
                            'code' => $data['code'],
                            'name' => $data['name'],
                            'address' => $data['address'] ?? [],
                        ]);
                    })
                    ->authorize(fn ($record): bool => (bool) (
                        Auth::user()?->hasRole('Super Admin')
                        || ((string) Auth::user()?->tenant_id === (string) $record->tenant_id)
                    )),
                Tables\Actions\DeleteAction::make()
                    ->authorize(fn ($record): bool => (bool) (
                        Auth::user()?->hasRole('Super Admin')
                        || ((string) Auth::user()?->tenant_id === (string) $record->tenant_id)
                    )),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->where('tenant_id', $this->getOwnerRecord()->id)
                ->when(Auth::user()?->tenant_id, fn (Builder $scoped) => $scoped->where('tenant_id', Auth::user()->tenant_id))
            );
    }
}
