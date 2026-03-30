<?php

namespace App\Filament\CampusManager\Resources;

use App\Filament\CampusManager\Resources\IncidentResource\Pages;
use App\Models\Incident;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class IncidentResource extends Resource
{
    protected static ?string $model = Incident::class;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationLabel = 'Incidents';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 6;

    // Hidden - replaced by Emergency/MedicalEmergencies and Emergency/IncidentReports pages
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Incident Details')
                    ->schema([
                        Forms\Components\TextInput::make('type')
                            ->required(),
                        Forms\Components\TextInput::make('note'),
                        Forms\Components\Select::make('status')
                            ->options([
                                'Open' => 'Open',
                                'Closed' => 'Closed',
                            ])
                            ->default('Open')
                            ->required(),
                    ]),
                Forms\Components\Section::make('Closure')
                    ->schema([
                        Forms\Components\Textarea::make('closure_note')
                            ->maxLength(1000)
                            ->rows(3)
                            ->label('Closure Note'),
                    ])
                    ->visible(fn (?Incident $record) => $record !== null && $record->status === 'Open'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id'),
                Tables\Columns\TextColumn::make('type'),
                Tables\Columns\TextColumn::make('status'),
                Tables\Columns\TextColumn::make('note'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('hostel')
                    ->relationship('hostel', 'name'),
                
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'LateReturn' => 'Late Return',
                        'MissedAttendance' => 'Missed Attendance',
                        'EmergencyExit' => 'Emergency Exit',
                        'Security' => 'Security',
                    ]),
                
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'Open' => 'Open',
                        'Closed' => 'Closed',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('close')
                    ->label('Close Incident')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->form([
                        Forms\Components\Textarea::make('closure_note')
                            ->required()
                            ->maxLength(1000)
                            ->label('Closure Note'),
                    ])
                    ->action(function (Incident $record, array $data) {
                        $record->close(auth()->id(), $data['closure_note']);
                    })
                    ->hidden(fn (Incident $record) => $record->status === 'Closed')
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('opened_at', 'desc');
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
            'index' => Pages\ListIncidents::route('/'),
            'create' => Pages\CreateIncident::route('/create'),
            'view' => Pages\ViewIncident::route('/{record}'),
            'edit' => Pages\EditIncident::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->tenant_id !== null;
    }

    /**
     * Tenant scoping: filters incidents by the authenticated user's tenant_id.
     * Falls back to the tenant() helper for subdomain-based tenancy resolution.
     * canAccess() above ensures only users with a tenant_id can access this resource,
     * so $tenantId should always be populated when this query runs.
     */
    public static function getEloquentQuery(): Builder
    {
        $tenantId = auth()->user()?->tenant_id ?? (function_exists('tenant') && tenant() ? tenant()->id : null);

        return parent::getEloquentQuery()
            ->when($tenantId, fn (Builder $q) => $q->where('tenant_id', $tenantId));
    }
}

