<?php

namespace App\Filament\CampusManager\Resources;

use App\Filament\CampusManager\Resources\GateEntryResource\Pages;
use App\Models\GateEntry;
use App\Support\Feature;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class GateEntryResource extends Resource
{
    protected static ?string $model = GateEntry::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationGroup = 'Security';

    protected static ?int $navigationSort = 20;

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();

        return Feature::isEnabled('gate_module') && $user && $user->hasAnyRole(['Guard', 'Campus Manager', 'Rector']);
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Select::make('event')
                    ->options([
                        'entry' => 'Entry',
                        'exit' => 'Exit',
                        'emergency_exit' => 'Emergency Exit',
                        'manual_override' => 'Manual Override',
                    ])
                    ->required(),
                DateTimePicker::make('occurred_at')
                    ->default(now())
                    ->required(),
                Toggle::make('was_offline')->label('Captured Offline'),
                Textarea::make('notes')->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // In tenant database context, we don't need tenant_id filtering
            ->defaultSort('occurred_at', 'desc')
            ->columns([
                BadgeColumn::make('event')
                    ->colors([
                        'success' => ['entry'],
                        'danger' => ['exit', 'emergency_exit'],
                        'warning' => ['manual_override'],
                    ]),
                TextColumn::make('student.name')->label('Student')->default('-'),
                TextColumn::make('guardUser.name')->label('Guard')->default('-'),
                TextColumn::make('occurred_at')->dateTime('d M Y H:i'),
                TextColumn::make('source')->badge(),
            ])
            ->filters([
                SelectFilter::make('event')->options([
                    'entry' => 'Entry',
                    'exit' => 'Exit',
                    'emergency_exit' => 'Emergency Exit',
                    'manual_override' => 'Manual Override',
                ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Log Gate Event')
                    ->visible(fn () => Auth::user()?->hasRole('Guard')),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGateEntries::route('/'),
            'create' => Pages\CreateGateEntry::route('/create'),
        ];
    }
}
