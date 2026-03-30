<?php

namespace App\Filament\CollegeMgmt\Resources;

use App\Domain\Tickets\Models\Ticket;
use App\Filament\CollegeMgmt\Resources\TicketResource\Pages;
use App\Models\User;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class TicketResource extends Resource
{
    protected static ?string $model = Ticket::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationLabel = 'Tickets';

    protected static ?string $modelLabel = 'Ticket';

    protected static ?string $pluralModelLabel = 'Tickets';

    protected static ?string $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        // Read-only - no form editing
        return $form->schema([]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $tenantId = Auth::user()?->tenant_id;
                if ($tenantId) {
                    $query->where('tenant_id', $tenantId);
                }
                return $query->with(['hostel', 'reporterStudent.user', 'reporterUser', 'assigneeUser']);
            })
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->limit(50),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'warning' => 'open',
                        'info' => 'in_progress',
                        'secondary' => 'on_hold',
                        'success' => 'resolved',
                        'danger' => 'closed',
                    ]),
                Tables\Columns\BadgeColumn::make('priority')
                    ->colors([
                        'success' => 'low',
                        'warning' => 'medium',
                        'danger' => 'high',
                    ]),
                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state): string => $state ? ucwords(str_replace(['_', '-'], ' ', $state)) : '—'),
                Tables\Columns\TextColumn::make('assigneeUser.name')
                    ->label('Assignee')
                    ->placeholder('Unassigned'),
                Tables\Columns\TextColumn::make('reporter_name')
                    ->label('Reporter'),
                Tables\Columns\TextColumn::make('hostel.name')
                    ->label('Hostel'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'open' => 'Open',
                        'in_progress' => 'In Progress',
                        'on_hold' => 'On Hold',
                        'resolved' => 'Resolved',
                        'closed' => 'Closed',
                    ])
                    ->multiple(),
                Tables\Filters\SelectFilter::make('priority')
                    ->options([
                        'low' => 'Low',
                        'medium' => 'Medium',
                        'high' => 'High',
                    ])
                    ->multiple(),
                Tables\Filters\SelectFilter::make('category')
                    ->options(function (): array {
                        $known = [
                            'housekeeping' => 'Housekeeping',
                            'maintenance' => 'Maintenance',
                            'security' => 'Security',
                            'laundry' => 'Laundry',
                            'other' => 'Other',
                        ];

                        $dbValues = Ticket::query()
                            ->whereNotNull('category')
                            ->distinct()
                            ->pluck('category')
                            ->filter()
                            ->mapWithKeys(fn (string $value) => [$value => ucwords(str_replace(['_', '-'], ' ', $value))])
                            ->toArray();

                        return array_merge($known, $dbValues);
                    })
                    ->multiple(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([10, 25, 50, 100]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Ticket Information')
                    ->schema([
                        Infolists\Components\TextEntry::make('title'),
                        Infolists\Components\TextEntry::make('description'),
                        Infolists\Components\TextEntry::make('category')
                            ->badge(),
                        Infolists\Components\TextEntry::make('priority')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'low' => 'success',
                                'medium' => 'warning',
                                'high' => 'danger',
                            }),
                        Infolists\Components\TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'open' => 'warning',
                                'in_progress' => 'info',
                                'on_hold' => 'secondary',
                                'resolved' => 'success',
                                'closed' => 'danger',
                            }),
                    ])
                    ->columns(2),
                Infolists\Components\Section::make('Assignment')
                    ->schema([
                        Infolists\Components\TextEntry::make('reporter_name')
                            ->label('Reporter'),
                        Infolists\Components\TextEntry::make('assigneeUser.name')
                            ->label('Assignee')
                            ->placeholder('Unassigned'),
                        Infolists\Components\TextEntry::make('hostel.name')
                            ->label('Hostel'),
                    ])
                    ->columns(2),
                Infolists\Components\Section::make('Timestamps')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('updated_at')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('closed_at')
                            ->dateTime()
                            ->placeholder('Not closed'),
                    ])
                    ->columns(3),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTickets::route('/'),
            'view' => Pages\ViewTicket::route('/{record}'),
        ];
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();
        return $user && ($user->hasRole('College Management') || $user->hasRole('College Mgmt'));
    }

    public static function canCreate(): bool
    {
        return false; // Read-only
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canView($record): bool
    {
        return static::canAccess();
    }

    public static function canEdit($record): bool
    {
        return false; // Read-only
    }

    public static function canDelete($record): bool
    {
        return false; // Read-only
    }
}
