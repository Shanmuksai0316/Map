<?php

namespace App\Filament\CampusManager\Resources;

use App\Domain\Checklists\Models\ChecklistTemplate;
use App\Domain\Checklists\Support\ChecklistRole;
use App\Filament\CampusManager\Resources\ChecklistTemplateResource\Pages;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ChecklistTemplateResource extends Resource
{
    protected static ?string $model = ChecklistTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Checklist Templates';

    protected static ?string $navigationGroup = 'Checklist';

    protected static ?int $navigationSort = 3;

    public static function shouldRegisterNavigation(): bool
    {
        // Replaced by Checklist Configuration page (one checklist per role).
        return false;
    }

    public static function canViewAny(): bool
    {
        // Block direct URL access to /campus-manager/checklist-templates
        // Use the Checklist Configuration page instead.
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Template Details')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Template Name')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(1),
                        
                        Forms\Components\Select::make('role')
                            ->label('Target Role')
                            ->options(ChecklistRole::options())
                            ->required()
                            ->native(false)
                            ->columnSpan(1),
                    ]),
                
                Forms\Components\Section::make('Checklist Items')
                    ->description('Maximum 10 items per checklist. Each item needs a unique code and a display label.')
                    ->schema([
                        Forms\Components\Repeater::make('tasks')
                            ->schema([
                                Forms\Components\TextInput::make('code')
                                    ->label('Item Code')
                                    ->required()
                                    ->maxLength(50)
                                    ->placeholder('e.g., CHECK_ROOMS')
                                    ->helperText('Unique identifier (uppercase, underscores)')
                                    ->regex('/^[A-Z][A-Z0-9_]*$/')
                                    ->validationMessages([
                                        'regex' => 'Code must be uppercase letters, numbers, and underscores only (e.g., CHECK_ROOMS)',
                                    ]),
                                Forms\Components\TextInput::make('label')
                                    ->label('Display Label')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g., Check all rooms are clean'),
                                Forms\Components\Toggle::make('require_photo')
                                    ->label('Require Photo')
                                    ->default(false)
                                    ->helperText('Staff must upload photo to complete this item'),
                                Forms\Components\Toggle::make('require_comment')
                                    ->label('Require Comment')
                                    ->default(false)
                                    ->helperText('Staff must add comment to complete this item'),
                            ])
                            ->columns(2)
                            ->defaultItems(1)
                            ->minItems(1)
                            ->maxItems(10)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? $state['code'] ?? 'New Item')
                            ->addActionLabel('Add Item')
                            ->reorderable()
                            ->helperText('You can add up to 10 checklist items'),
                    ]),
                
                Forms\Components\Section::make('Settings')
                    ->schema([
                        Forms\Components\Toggle::make('active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Only active templates will appear on staff app'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Template Name')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('role')
                    ->label('Target Role')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match($state) {
                        'CampusManager' => 'Campus Manager',
                        'HKSupervisor' => 'HK Supervisor',
                        'RMSupervisor' => 'RM Supervisor',
                        'LaundryManager' => 'Laundry Manager',
                        'SportsManager' => 'Sports Manager',
                        default => $state,
                    })
                    ->color(fn (string $state) => match($state) {
                        'CampusManager' => 'info',
                        'Warden' => 'primary',
                        'HKSupervisor' => 'warning',
                        'RMSupervisor' => 'danger',
                        'Guard' => 'success',
                        'LaundryManager' => 'gray',
                        'SportsManager' => 'primary',
                        default => 'gray',
                    }),
                
                Tables\Columns\TextColumn::make('tasks_count')
                    ->label('Tasks')
                    ->getStateUsing(fn ($record) => count($record->tasks ?? []))
                    ->badge()
                    ->color('gray'),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created Date')
                    ->date('d M Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label('Target Role')
                    ->options(ChecklistRole::options()),
                
                Tables\Filters\TernaryFilter::make('active')
                    ->label('Active Only'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalHeading(fn ($record) => $record->title)
                    ->modalWidth('lg'),
                Tables\Actions\EditAction::make()
                    ->modalHeading(fn ($record) => 'Edit: ' . $record->title)
                    ->modalWidth('lg'),
            ])
            ->bulkActions([])
            ->defaultSort('title');
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
            'index' => Pages\ListChecklistTemplates::route('/'),
            'create' => Pages\CreateChecklistTemplate::route('/create'),
            'view' => Pages\ViewChecklistTemplate::route('/{record}'),
            'edit' => Pages\EditChecklistTemplate::route('/{record}/edit'),
        ];
    }
}

