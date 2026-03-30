<?php

namespace App\Filament\CampusManager\Resources;

use App\Domain\Checklists\Models\ChecklistInstance;
use App\Filament\CampusManager\Resources\ChecklistInstanceResource\Pages;
use App\Services\FeatureFlagsService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Carbon;

class ChecklistInstanceResource extends Resource
{
    protected static ?string $model = ChecklistInstance::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Staff Checklists';

    protected static ?string $navigationGroup = 'Checklist';

    protected static ?string $modelLabel = 'Staff Checklist';

    protected static ?string $pluralModelLabel = 'Staff Checklists';

    protected static ?int $navigationSort = 2;

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if (! $user->hasAnyRole(['Campus Manager', 'Super Admin', 'Rector'])) {
            return false;
        }

        $enabled = app(FeatureFlagsService::class)->enabled('checklists_module', $user->tenant_id);

        return $enabled;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('date')
                    ->disabled(),
                Forms\Components\TextInput::make('role')
                    ->disabled(),
                Forms\Components\TextInput::make('status')
                    ->disabled(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query): Builder {
                // Hide persistent "draft" instances; show only real submissions/daily checklists.
                return $query->where('shift', '!=', 'Persistent');
            })
            ->columns([
                // Staff Name
                Tables\Columns\TextColumn::make('assignee.name')
                    ->label('Staff Name')
                    ->searchable()
                    ->sortable(),

                // Role
                Tables\Columns\TextColumn::make('role')
                    ->label('Role')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match($state) {
                        'CampusManager' => 'Campus Manager',
                        'HKSupervisor' => 'HK Supervisor',
                        'RMSupervisor' => 'RM Supervisor',
                        'LaundryManager' => 'Laundry Manager',
                        'SportsManager' => 'Sports Manager',
                        default => $state,
                    }),

                // Progress
                Tables\Columns\TextColumn::make('progress')
                    ->label('Progress')
                    ->getStateUsing(fn (ChecklistInstance $record): string =>
                        "{$record->completed_tasks}/{$record->total_tasks}"
                    )
                    ->badge()
                    ->color(fn (ChecklistInstance $record) => 
                        $record->completed_tasks === $record->total_tasks ? 'success' : 'warning'
                    ),

                Tables\Columns\TextColumn::make('date')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('submitted_at')
                    ->label('Submitted')
                    ->dateTime('d M Y, h:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('review_status')
                    ->label('Review')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'Approved' => 'success',
                        'SentBack' => 'warning',
                        null, '' => 'gray',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),

                // Status
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Pending' => 'warning',
                        'Submitted' => 'success',
                        default => 'gray',
                    }),
            ])
            ->filters([
                Tables\Filters\Filter::make('date_range')
                    ->label('Date Range')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('From')
                            ->default(now()->subDays(7)->toDateString()),
                        Forms\Components\DatePicker::make('to')
                            ->label('To')
                            ->default(now()->toDateString()),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $from = !empty($data['from']) ? Carbon::parse($data['from'])->toDateString() : null;
                        $to = !empty($data['to']) ? Carbon::parse($data['to'])->toDateString() : null;

                        return $query
                            ->when($from, fn (Builder $q) => $q->whereDate('date', '>=', $from))
                            ->when($to, fn (Builder $q) => $q->whereDate('date', '<=', $to));
                    }),

                Tables\Filters\SelectFilter::make('completion')
                    ->label('Completion')
                    ->options([
                        'completed' => 'Completed (100%)',
                        'incomplete' => 'Incomplete',
                    ])
                    ->default('completed')
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;
                        if ($value === 'completed') {
                            return $query->whereColumn('completed_tasks', '>=', 'total_tasks')
                                ->where('total_tasks', '>', 0);
                        }
                        if ($value === 'incomplete') {
                            return $query->where(function (Builder $q) {
                                $q->whereColumn('completed_tasks', '<', 'total_tasks')
                                  ->orWhere('total_tasks', '=', 0);
                            });
                        }
                        return $query;
                    }),

                Tables\Filters\SelectFilter::make('role')
                    ->label('Role')
                    ->options([
                        'CampusManager' => 'Campus Manager',
                        'Warden' => 'Warden',
                        'HKSupervisor' => 'HK Supervisor',
                        'RMSupervisor' => 'RM Supervisor',
                        'Guard' => 'Security Guard',
                        'LaundryManager' => 'Laundry Manager',
                        'SportsManager' => 'Sports Manager',
                    ]),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'Pending' => 'Pending',
                        'Submitted' => 'Submitted',
                    ]),

                Tables\Filters\SelectFilter::make('review_status')
                    ->label('Review Status')
                    ->options([
                        'Approved' => 'Approved',
                        'SentBack' => 'Sent Back',
                        'Pending' => 'Pending',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('approve')
                    ->label('Approve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(function (ChecklistInstance $record): bool {
                        if ($record->status !== 'Submitted') {
                            return false;
                        }

                        if (! in_array($record->review_status, ['Pending', 'SentBack'], true)) {
                            return false;
                        }

                        $user = auth()->user();

                        return $user && $user->can('approve', $record);
                    })
                    ->requiresConfirmation()
                    ->modalHeading('Approve Checklist')
                    ->modalDescription('Are you sure you want to approve this checklist?')
                    ->action(function (ChecklistInstance $record): void {
                        $response = Http::withToken(auth()->user()->createToken('filament')->plainTextToken)
                            ->post(url("/api/v1/checklists/{$record->id}/approve"));

                        if ($response->successful()) {
                            $record->refresh();
                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Checklist Approved')
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Failed to approve')
                                ->body($response->json('message', 'An error occurred'))
                                ->send();
                        }
                    }),
                Tables\Actions\Action::make('send_back')
                    ->label('Send Back')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->visible(function (ChecklistInstance $record): bool {
                        if ($record->status !== 'Submitted') {
                            return false;
                        }

                        if (! in_array($record->review_status, ['Pending', 'SentBack'], true)) {
                            return false;
                        }

                        $user = auth()->user();

                        return $user && $user->can('sendBack', $record);
                    })
                    ->form([
                        Forms\Components\Textarea::make('note')
                            ->label('Reason (optional)')
                            ->maxLength(500)
                            ->rows(3),
                    ])
                    ->action(function (ChecklistInstance $record, array $data): void {
                        $response = Http::withToken(auth()->user()->createToken('filament')->plainTextToken)
                            ->post(url("/api/v1/checklists/{$record->id}/send-back"), [
                                'note' => $data['note'] ?? null,
                            ]);

                        if ($response->successful()) {
                            $record->refresh();
                            \Filament\Notifications\Notification::make()
                                ->success()
                                ->title('Checklist Sent Back')
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Failed to send back')
                                ->body($response->json('message', 'An error occurred'))
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([])
            ->defaultSort('date', 'desc')
            ->emptyStateHeading('No checklists found')
            ->emptyStateDescription('No checklists match your filters.')
            ->poll('30s');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChecklistInstances::route('/'),
            'view' => Pages\ViewChecklistInstance::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['assignee', 'manager', 'items']);

        $user = auth()->user();

        if ($user && ! $user->hasAnyRole(['Campus Manager', 'Rector', 'Super Admin'])) {
            $query->where('assignee_user_id', $user->id);
        }

        return $query;
    }
}
