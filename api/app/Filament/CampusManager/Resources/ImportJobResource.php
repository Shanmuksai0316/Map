<?php

namespace App\Filament\CampusManager\Resources;

use App\Filament\CampusManager\Resources\ImportJobResource\Pages;
use App\Filament\CampusManager\Resources\ImportJobResource\Pages\StartImport;
use App\Filament\CampusManager\Resources\ImportJobResource\RelationManagers\ErrorsRelationManager;
use App\Models\ImportJob;
use App\Services\Imports\RoomAllotmentImportService;
use App\Services\Imports\StudentImportService;
use Filament\Forms;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class ImportJobResource extends Resource
{
    protected static ?string $model = ImportJob::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-down-on-square-stack';

    protected static ?string $navigationGroup = 'Student Management';

    protected static ?string $navigationLabel = 'Bulk Upload Students';

    protected static ?int $navigationSort = 5;

    public static function getModelLabel(): string
    {
        return 'Import Job';
    }

    public static function getPluralModelLabel(): string
    {
        return 'Import Jobs';
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Grid::make()
                ->columns(2)
                ->schema([
                    Select::make('kind')
                        ->options([
                            'students' => 'Students',
                            'room_allotments' => 'Room Allotments',
                        ])
                        ->disabled(),
                    Select::make('status')
                        ->options([
                            'DryRun' => 'Dry Run',
                            'DryRunErrors' => 'Dry Run Errors',
                            'DryRunOK' => 'Dry Run OK',
                            'Queued' => 'Queued',
                            'Processing' => 'Processing',
                            'Completed' => 'Completed',
                            'Failed' => 'Failed',
                        ])
                        ->disabled(),
                    TextInput::make('total_rows')->numeric()->disabled(),
                    TextInput::make('error_rows')->numeric()->disabled(),
                    TextInput::make('processed_rows')->numeric()->disabled(),
                    TextInput::make('inserted_rows')->numeric()->disabled(),
                    TextInput::make('updated_rows')->numeric()->disabled(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kind')
                    ->badge()
                    ->label('Type')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'students' => 'Students',
                        'room_allotments' => 'Room Allotments',
                        default => ucfirst(str_replace('_', ' ', $state)),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'students' => 'primary',
                        'room_allotments' => 'warning',
                        default => 'gray',
                    }),
                BadgeColumn::make('status')
                    ->colors([
                        'gray' => ['DryRun'],
                        'warning' => ['DryRunErrors'],
                        'info' => ['Queued', 'Processing'],
                        'success' => ['DryRunOK', 'Completed'],
                        'danger' => ['Failed'],
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'DryRun' => 'Dry Run',
                        'DryRunErrors' => 'Dry Run Errors',
                        'DryRunOK' => 'Dry Run OK',
                        'Queued' => 'Queued',
                        'Processing' => 'Processing',
                        'Completed' => 'Completed',
                        'Failed' => 'Failed',
                        default => $state,
                    })
                    ->label('Status'),
                TextColumn::make('total_rows')->sortable(),
                TextColumn::make('processed_rows')->sortable(),
                TextColumn::make('inserted_rows')->sortable(),
                TextColumn::make('updated_rows')->sortable(),
                TextColumn::make('error_rows')->sortable(),
                TextColumn::make('created_at')->dateTime('d M Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('kind')
                    ->options([
                        'students' => 'Students',
                        'room_allotments' => 'Room Allotments',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'DryRun' => 'Dry Run',
                        'DryRunErrors' => 'Dry Run Errors',
                        'DryRunOK' => 'Dry Run OK',
                        'Queued' => 'Queued',
                        'Processing' => 'Processing',
                        'Completed' => 'Completed',
                        'Failed' => 'Failed',
                    ]),
            ])
            ->actions([
                Tables\Actions\Action::make('commit')
                    ->label('Commit')
                    ->icon('heroicon-o-paper-airplane')
                    ->requiresConfirmation()
                    ->visible(fn (ImportJob $record): bool => $record->status === 'DryRunOK')
                    ->action(function (ImportJob $record): void {
                        match ($record->kind) {
                            'students' => app(StudentImportService::class)->commit($record),
                            'room_allotments' => app(RoomAllotmentImportService::class)->commit($record),
                            default => null,
                        };
                    })
                    ->successNotificationTitle('Commit triggered')
                    ->failureNotificationTitle('Unable to commit import'),
                Tables\Actions\ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ErrorsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListImportJobs::route('/'),
            // Register the explicit start-import path BEFORE the generic {record} view route
            // so that /start-import is not treated as a record identifier.
            'start-import' => Pages\StartImport::route('/start-import'),
            'view' => Pages\ViewImportJob::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getEloquentQuery()->count();
    }

    public static function getEloquentQuery(): Builder
    {
        // In tenant database context, we don't need tenant_id filtering
        return parent::getEloquentQuery();
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['kind', 'status'];
    }

    public static function getNavigationPages(): array
    {
        return [
            StartImport::class,
        ];
    }
}
