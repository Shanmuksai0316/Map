<?php

namespace App\Filament\CampusManager\Resources;

use App\Filament\CampusManager\Resources\HostelResource\Pages;
use App\Models\Hostel;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class HostelResource extends Resource
{
    protected static ?string $model = Hostel::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Hostels';

    protected static ?int $navigationSort = 2;

    protected static ?string $tenantOwnershipRelationshipName = 'tenant';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Basic Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('code')
                                    ->label('Code')
                                    ->required()
                                    ->maxLength(16)
                                    // Code is set at onboarding and cannot be changed later.
                                    ->disabled(fn ($record) => filled($record))
                                    ->dehydrated(fn ($record) => blank($record))
                                    ->helperText(fn ($record) => $record && $record->tenant && $record->tenant->status === \App\Enums\TenantStatus::ACTIVE 
                                        ? 'Code cannot be changed after tenant activation' 
                                        : null),
                                TextInput::make('name')
                                    ->label('Name')
                                    ->required()
                                    ->maxLength(120)
                                    // Name is fixed after creation – view-only in edit screen.
                                    ->disabled(fn ($record) => filled($record))
                                    ->dehydrated(fn ($record) => blank($record))
                                    ->helperText(fn ($record) => $record && $record->tenant && $record->tenant->status === \App\Enums\TenantStatus::ACTIVE 
                                        ? 'Name cannot be changed after tenant activation' 
                                        : null),
                            ]),
                        TextInput::make('gender_mode')
                            ->label('Gender')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('Gender is configured during onboarding and cannot be changed here.'),
                    ]),
                // Branding (logo) is managed at the Tenant level from the Admin panel.
                // Campus Manager can view branding via the header logo, but cannot
                // upload or change logos from this screen to avoid tenancy issues.
                Section::make('Rules & Timings')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TimePicker::make('curfew_time')
                                    ->label('Curfew Time')
                                    ->required()
                                    ->helperText('Campus Manager can adjust curfew time'),
                                Toggle::make('overnight_enabled')
                                    ->label('Overnight Enabled'),
                            ]),
                        Grid::make(2)
                            ->schema([
                                TimePicker::make('visiting_start')
                                    ->label('Visiting Start'),
                                TimePicker::make('visiting_end')
                                    ->label('Visiting End'),
                            ]),
                    ]),
                Section::make('Address')
                    ->schema([
                        TextInput::make('address.line_1')
                            ->label('Address Line 1')
                            ->maxLength(255),
                        TextInput::make('address.city')
                            ->label('City')
                            ->maxLength(120),
                        TextInput::make('address.state')
                            ->label('State')
                            ->maxLength(120),
                        TextInput::make('address.pincode')
                            ->label('Pincode')
                            ->maxLength(10),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        $tenantId = Auth::user()?->tenant_id;
        
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
            )
            ->columns([
                TextColumn::make('code')->label('Code')->searchable()->sortable(),
                TextColumn::make('name')->label('Name')->searchable()->sortable(),
                TextColumn::make('campus.name')->label('Campus')->sortable(),
                TextColumn::make('gender_mode')->label('Gender Mode'),
                ToggleColumn::make('overnight_enabled')
                    ->label('Overnight Out-Pass')
                    ->disabled(fn () => ! Auth::user()?->tenant?->addon_security),
                TextColumn::make('curfew_time')->label('Curfew')->time('H:i'),
            ])
            ->filters([
                SelectFilter::make('campus_id')->relationship('campus', 'name'),
                SelectFilter::make('gender_mode')->options([
                    'Male' => 'Male',
                    'Female' => 'Female',
                    'Coed' => 'Coed',
                ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('enableOvernight')
                        ->label('Enable Overnight')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn (Builder $records) => $records->update(['overnight_enabled' => true]))
                        ->visible(fn () => Auth::user()?->tenant?->addon_security),
                    Tables\Actions\BulkAction::make('disableOvernight')
                        ->label('Disable Overnight')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn (Builder $records) => $records->update(['overnight_enabled' => false]))
                        ->visible(fn () => Auth::user()?->tenant?->addon_security),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Hostel Information')
                    ->schema([
                        Infolists\Components\ImageEntry::make('settings.logo_path')
                            ->label('Logo')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->size(72)
                            ->circular()
                            ->alignCenter(),
                        Infolists\Components\TextEntry::make('tenant.name')
                            ->label('Tenant'),
                        Infolists\Components\TextEntry::make('campus.name')
                            ->label('Campus'),
                        Infolists\Components\TextEntry::make('code')
                            ->label('Code'),
                        Infolists\Components\TextEntry::make('name')
                            ->label('Name'),
                        Infolists\Components\TextEntry::make('gender_mode')
                            ->label('Gender')
                            ->formatStateUsing(fn ($state) => $state ? ucfirst(strtolower($state)) : null),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime('d M Y H:i'),
                    ])
                    ->columns(2),
                Infolists\Components\Section::make('Timings & Rules')
                    ->schema([
                        Infolists\Components\TextEntry::make('curfew_time')
                            ->label('Curfew Time')
                            ->time('H:i'),
                        Infolists\Components\TextEntry::make('overnight_enabled')
                            ->label('Overnight Out-Pass')
                            ->badge()
                            ->formatStateUsing(fn ($state) => $state ? 'Enabled' : 'Disabled')
                            ->color(fn ($state) => $state ? 'success' : 'secondary'),
                        Infolists\Components\TextEntry::make('visiting_start')
                            ->label('Visiting Start')
                            ->time('H:i'),
                        Infolists\Components\TextEntry::make('visiting_end')
                            ->label('Visiting End')
                            ->time('H:i'),
                    ])
                    ->columns(2),
                Infolists\Components\Section::make('Address')
                    ->schema([
                        Infolists\Components\TextEntry::make('address.line_1')
                            ->label('Address Line 1')
                            ->placeholder('Near Skyline Main Road'),
                        Infolists\Components\TextEntry::make('address.city')
                            ->label('City')
                            ->placeholder('Bengaluru'),
                        Infolists\Components\TextEntry::make('address.state')
                            ->label('State')
                            ->placeholder('Karnataka'),
                        Infolists\Components\TextEntry::make('address.pincode')
                            ->label('Pincode')
                            ->placeholder('560001'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListHostels::route('/'),
            'create' => Pages\CreateHostel::route('/create'),
            'view' => Pages\ViewHostel::route('/{record}'),
            'edit' => Pages\EditHostel::route('/{record}/edit'),
        ];
    }
}
