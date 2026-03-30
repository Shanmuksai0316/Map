<?php

namespace App\Filament\CampusManager\Resources;

use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketComment;
use App\Filament\CampusManager\Resources\TicketResource\Pages;
use App\Models\User;
use Filament\Forms;
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

    protected static ?int $navigationSort = 3;

    // Hidden - replaced by Housekeeping and Maintenance Request pages
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Ticket Details')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(160),
                        Forms\Components\Select::make('category')
                            ->options([
                                'housekeeping' => 'Housekeeping',
                                'maintenance' => 'Maintenance',
                                'security' => 'Security',
                                'laundry' => 'Laundry',
                                'other' => 'Other',
                            ])
                            ->required(),
                        Forms\Components\Select::make('priority')
                            ->options([
                                'low' => 'Low',
                                'medium' => 'Medium',
                                'high' => 'High',
                            ])
                            ->default('medium')
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'open' => 'Open',
                                'in_progress' => 'In Progress',
                                'on_hold' => 'On Hold',
                                'resolved' => 'Resolved',
                                'closed' => 'Closed',
                            ])
                            ->required(),
                        Forms\Components\Textarea::make('description')
                            ->required()
                            ->rows(4),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
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
                    ->color('gray'),
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
                    ->options([
                        'housekeeping' => 'Housekeeping',
                        'maintenance' => 'Maintenance',
                        'security' => 'Security',
                        'laundry' => 'Laundry',
                        'other' => 'Other',
                    ])
                    ->multiple(),
                Tables\Filters\Filter::make('mine')
                    ->label('My Assignments')
                    ->query(fn (Builder $query): Builder => $query->where('assignee_user_id', Auth::id()))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('assign')
                    ->label('Assign')
                    ->icon('heroicon-o-user-plus')
                    ->form([
                        Forms\Components\Select::make('assignee_user_id')
                            ->label('Assign to')
                            ->options(function () {
                                // In tenant database context, we don't need tenant_id filtering
                                return User::query()
                                    ->whereHas('roles', function ($query) {
                                        $query->whereIn('name', ['HKSupervisor', 'RMSupervisor', 'CampusManager']);
                                    })
                                    ->pluck('name', 'id');
                            })
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (Ticket $record, array $data): void {
                        $record->update([
                            'assignee_user_id' => $data['assignee_user_id'],
                            'updated_by_user_id' => Auth::id(),
                        ]);
                    })
                    ->visible(fn (Ticket $record): bool => Auth::user()->can('assign', $record)),
                Tables\Actions\Action::make('change_status')
                    ->label('Change Status')
                    ->icon('heroicon-o-arrow-path')
                    ->form([
                        Forms\Components\Select::make('status')
                            ->label('New Status')
                            ->options(function (Ticket $record) {
                                $allowedTransitions = $record->getAllowedTransitions();
                                $statusOptions = [
                                    'open' => 'Open',
                                    'in_progress' => 'In Progress',
                                    'on_hold' => 'On Hold',
                                    'resolved' => 'Resolved',
                                    'closed' => 'Closed',
                                ];
                                
                                return array_intersect_key($statusOptions, array_flip($allowedTransitions));
                            })
                            ->required(),
                    ])
                    ->action(function (Ticket $record, array $data): void {
                        $updateData = [
                            'status' => $data['status'],
                            'updated_by_user_id' => Auth::id(),
                        ];

                        if (in_array($data['status'], ['resolved', 'closed'])) {
                            $updateData['closed_at'] = now();
                        }

                        $record->update($updateData);
                    })
                    ->visible(fn (Ticket $record): bool => Auth::user()->can('transition', $record)),
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
                Infolists\Components\Section::make('Assignment & Timeline')
                    ->schema([
                        Infolists\Components\TextEntry::make('reporter_name')
                            ->label('Reporter'),
                        Infolists\Components\TextEntry::make('assigneeUser.name')
                            ->label('Assignee')
                            ->placeholder('Unassigned'),
                        Infolists\Components\TextEntry::make('hostel.name')
                            ->label('Hostel'),
                        Infolists\Components\TextEntry::make('sla_due_at')
                            ->label('SLA Due')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('Updated')
                            ->dateTime(),
                    ])
                    ->columns(2),
                Infolists\Components\Section::make('Comments')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('comments')
                            ->schema([
                                Infolists\Components\TextEntry::make('body'),
                                Infolists\Components\TextEntry::make('author.name')
                                    ->label('Author'),
                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Posted')
                                    ->dateTime(),
                            ])
                            ->columns(3),
                    ])
                    ->collapsible(),
            ]);
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
            'index' => Pages\ListTickets::route('/'),
            'view' => Pages\ViewTicket::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            // In tenant database context, we don't need tenant_id filtering
            ->with(['hostel', 'reporterStudent.user', 'reporterUser', 'assigneeUser', 'comments.user']);

        // Apply role-based scoping
        if (Auth::user()->hasRole(['Warden', 'HKSupervisor', 'RMSupervisor', 'Guard', 'LaundryManager', 'SportsManager'])) {
            $userHostelIds = Auth::user()->hostel_ids ?? [];
            if (!empty($userHostelIds)) {
                $query->whereIn('hostel_id', $userHostelIds);
            }
        }

        return $query;
    }
}







