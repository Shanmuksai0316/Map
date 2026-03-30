<?php

namespace App\Filament\Resources\Admin;

use App\Filament\Resources\Admin\CommunicationResource\Pages;
use App\Models\Notice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

class CommunicationResource extends Resource
{
    protected static ?string $model = Notice::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationGroup = null; // Hidden from navigation per UI requirements

    protected static ?string $navigationLabel = 'Communications';

    protected static ?int $navigationSort = 5;

    public static function shouldRegisterNavigation(): bool
    {
        return false; // Hidden from navigation per UI requirements
    }

    protected static ?string $slug = 'communications';
    
    protected static ?string $modelLabel = 'Notice';
    
    protected static ?string $pluralModelLabel = 'Communications';

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('Super Admin');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Notice Details')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Notice Title')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\RichEditor::make('content')
                            ->label('Content')
                            ->required()
                            ->columnSpanFull(),
                    ]),
                Forms\Components\Section::make('Target Audience')
                    ->schema([
                        Forms\Components\Select::make('target_role')
                            ->label('Target Role')
                            ->options(function () {
                                return Role::whereIn('name', [
                                    'All Staff',
                                    'Campus Manager',
                                    'Warden',
                                    'HK Supervisor',
                                    'RM Supervisor',
                                    'Guard',
                                    'Laundry Manager',
                                    'Sports Manager',
                                ])->pluck('name', 'name')
                                    ->prepend('All Staff', 'all');
                            })
                            ->required()
                            ->default('all'),
                        Forms\Components\Select::make('target_hostel_id')
                            ->label('Target Hostel')
                            ->relationship('targetHostel', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('All Hostels'),
                        Forms\Components\Select::make('target_tenant_id')
                            ->label('Target Tenant')
                            ->relationship('targetTenant', 'name')
                            ->searchable()
                            ->preload()
                            ->placeholder('All Tenants'),
                    ])
                    ->columns(3),
                Forms\Components\Section::make('Publishing')
                    ->schema([
                        Forms\Components\DateTimePicker::make('publish_at')
                            ->label('Publish At')
                            ->default(now())
                            ->required(),
                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('Expires At')
                            ->after('publish_at'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Notice Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->limit(50),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('target_role')
                    ->label('Target Role')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(fn (?string $state): string => $state === 'all' ? 'All Staff' : ($state ?? 'All')),
                Tables\Columns\TextColumn::make('targetHostel.name')
                    ->label('Target Hostel')
                    ->placeholder('All Hostels'),
                Tables\Columns\TextColumn::make('targetTenant.name')
                    ->label('Target Tenant')
                    ->placeholder('All Tenants')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->getStateUsing(function (Notice $record): bool {
                        $now = now();
                        $published = $record->publish_at ? $record->publish_at->lte($now) : true;
                        $notExpired = $record->expires_at ? $record->expires_at->gte($now) : true;
                        return $published && $notExpired;
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('target_role')
                    ->label('Target Role')
                    ->options([
                        'all' => 'All Staff',
                        'Campus Manager' => 'Campus Manager',
                        'Warden' => 'Warden',
                        'HK Supervisor' => 'HK Supervisor',
                        'RM Supervisor' => 'RM Supervisor',
                        'Guard' => 'Guard',
                    ]),
                Tables\Filters\Filter::make('active')
                    ->label('Active Only')
                    ->query(function (Builder $query): Builder {
                        $now = now();
                        return $query
                            ->where(function ($q) use ($now) {
                                $q->whereNull('publish_at')->orWhere('publish_at', '<=', $now);
                            })
                            ->where(function ($q) use ($now) {
                                $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now);
                            });
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Notice Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('title')
                            ->label('Notice Name'),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime('d M Y H:i'),
                        Infolists\Components\TextEntry::make('target_role')
                            ->label('Target Role')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => $state === 'all' ? 'All Staff' : ($state ?? 'All')),
                        Infolists\Components\TextEntry::make('targetHostel.name')
                            ->label('Target Hostel')
                            ->default('All Hostels'),
                        Infolists\Components\TextEntry::make('targetTenant.name')
                            ->label('Target Tenant')
                            ->default('All Tenants'),
                        Infolists\Components\TextEntry::make('content')
                            ->label('Content')
                            ->html()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCommunications::route('/'),
            'create' => Pages\CreateCommunication::route('/create'),
            'view' => Pages\ViewCommunication::route('/{record}'),
            'edit' => Pages\EditCommunication::route('/{record}/edit'),
        ];
    }
}
