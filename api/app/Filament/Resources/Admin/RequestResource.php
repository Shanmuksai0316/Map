<?php

namespace App\Filament\Resources\Admin;

use App\Filament\Resources\Admin\RequestResource\Pages;
use App\Models\Ticket;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RequestResource extends Resource
{
    protected static ?string $model = Ticket::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationGroup = null; // Hidden from navigation per UI requirements

    protected static ?string $navigationLabel = 'Requests';

    protected static ?int $navigationSort = 4;

    public static function shouldRegisterNavigation(): bool
    {
        return false; // Hidden from navigation per UI requirements
    }

    protected static ?string $slug = 'requests';
    
    protected static ?string $modelLabel = 'Request';
    
    protected static ?string $pluralModelLabel = 'Requests';

    public static function canAccess(): bool
    {
        return auth()->check() && auth()->user()->hasRole('Super Admin');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['student.user', 'student.hostel', 'assignedTo', 'tenant']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultPaginationPageSize(25)
            ->paginationPageOptions([25, 50, 100])
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Request ID')
                    ->sortable()
                    ->weight('medium')
                    ->prefix('#')
                    ->color('primary'),
                Tables\Columns\TextColumn::make('student_room')
                    ->label('Student - Room')
                    ->getStateUsing(function (Ticket $record): string {
                        $studentName = $record->student?->user?->name ?? 'Unknown';
                        $allocation = $record->student?->roomAllocations()?->where('is_active', true)->with('roomBed.room')->first();
                        $roomNo = $allocation?->roomBed?->room?->room_no ?? 'N/A';
                        return "{$studentName} - {$roomNo}";
                    })
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('student.user', function ($q) use ($search) {
                            $q->where('name', 'like', "%{$search}%");
                        });
                    }),
                Tables\Columns\TextColumn::make('category')
                    ->label('Category')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'maintenance' => 'danger',
                        'housekeeping' => 'warning',
                        'security' => 'info',
                        'general' => 'gray',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'warning',
                        'in_progress' => 'info',
                        'resolved' => 'success',
                        'closed' => 'gray',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('tenant.name')
                    ->label('Tenant')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Submitted')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('Category')
                    ->options([
                        'maintenance' => 'Maintenance',
                        'housekeeping' => 'Housekeeping',
                        'security' => 'Security',
                        'general' => 'General',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'open' => 'Open',
                        'in_progress' => 'In Progress',
                        'resolved' => 'Resolved',
                        'closed' => 'Closed',
                    ]),
                Tables\Filters\SelectFilter::make('tenant_id')
                    ->label('Tenant')
                    ->relationship('tenant', 'name'),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From Date'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Until Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $q) => $q->whereDate('created_at', '>=', $data['from']))
                            ->when($data['until'], fn (Builder $q) => $q->whereDate('created_at', '<=', $data['until']));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalHeading(fn (Ticket $record) => "Request #{$record->id}")
                    ->modalContent(fn (Ticket $record) => view('filament.resources.request-details', ['record' => $record])),
            ])
            ->bulkActions([])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Request Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('id')
                            ->label('Request ID')
                            ->prefix('#'),
                        Infolists\Components\TextEntry::make('title')
                            ->label('Request Name'),
                        Infolists\Components\TextEntry::make('student.user.name')
                            ->label('Student Name'),
                        Infolists\Components\TextEntry::make('room_no')
                            ->label('Room Number')
                            ->getStateUsing(function (Ticket $record): string {
                                $allocation = $record->student?->roomAllocations()?->where('is_active', true)->with('roomBed.room')->first();
                                return $allocation?->roomBed?->room?->room_no ?? 'N/A';
                            }),
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Submitted')
                            ->dateTime('d M Y H:i'),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'open' => 'warning',
                                'in_progress' => 'info',
                                'resolved' => 'success',
                                'closed' => 'gray',
                                default => 'gray',
                            }),
                        Infolists\Components\TextEntry::make('assignedTo.name')
                            ->label('Assigned To')
                            ->default('Not Assigned'),
                        Infolists\Components\TextEntry::make('description')
                            ->label('Description')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRequests::route('/'),
            'view' => Pages\ViewRequest::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }
}

