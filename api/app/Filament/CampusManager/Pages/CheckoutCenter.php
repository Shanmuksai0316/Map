<?php

namespace App\Filament\CampusManager\Pages;

use App\Models\RoomAllocation;
use App\Services\Checkouts\CheckoutWorkflowService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class CheckoutCenter extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Rooms & Allocation';

    protected static ?int $navigationSort = 7;

    protected static ?string $navigationLabel = 'Checkout Center';

    protected static string $view = 'filament.campus-manager.pages.checkout-center';

    public function getHeading(): string
    {
        return 'Checkout Center';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                TextColumn::make('student.user.name')
                    ->label('Student')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('roomBed.room.number')
                    ->label('Room')
                    ->badge()
                    ->sortable(),
                TextColumn::make('roomBed.code')
                    ->label('Bed')
                    ->badge(),
                TextColumn::make('hostel.name')
                    ->label('Hostel')
                    ->badge()
                    ->sortable(),
                TextColumn::make('expected_checkout_at')
                    ->label('Expected Checkout')
                    ->formatStateUsing(function (RoomAllocation $record): string {
                        $date = $record->expected_checkout_at
                            ? Carbon::parse($record->expected_checkout_at)
                            : Carbon::parse($record->effective_from)->addMonths((int) config('checkouts.default_period_months', 18));
                        return $date->format('d M Y');
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        $months = (int) config('checkouts.default_period_months', 18);
                        $driver = $query->getModel()->getConnection()->getDriverName();

                        if ($driver === 'sqlite') {
                            return $query->orderByRaw(
                                "COALESCE(expected_checkout_at, datetime(effective_from, '+{$months} months')) {$direction}"
                            );
                        }

                        return $query->orderByRaw(
                            "COALESCE(expected_checkout_at, effective_from + interval '{$months} months') {$direction}"
                        );
                    })
                    ->description(function (RoomAllocation $record): string {
                        $date = $record->expected_checkout_at
                            ? Carbon::parse($record->expected_checkout_at)
                            : Carbon::parse($record->effective_from)->addMonths((int) config('checkouts.default_period_months', 18));
                        return $date->diffForHumans();
                    }),
                TextColumn::make('checkout_status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'completed' => 'success',
                        'in_progress' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('hostel_id')
                    ->relationship('hostel', 'name')
                    ->searchable()
                    ->label('Hostel'),
                SelectFilter::make('checkout_status')
                    ->options([
                        'pending' => 'Pending',
                        'in_progress' => 'In Progress',
                        'completed' => 'Completed',
                    ])
                    ->label('Status'),
                Tables\Filters\Filter::make('due_today')
                    ->label('Due Today')
                    ->query(fn (Builder $query): Builder => $query->whereDate('expected_checkout_at', Carbon::today())),
                Tables\Filters\Filter::make('due_this_week')
                    ->label('Due This Week')
                    ->query(fn (Builder $query): Builder => $query->whereBetween('expected_checkout_at', [Carbon::today(), Carbon::today()->addDays(7)])),
                Tables\Filters\Filter::make('due_this_month')
                    ->label('Due This Month')
                    ->query(fn (Builder $query): Builder => $query->whereBetween('expected_checkout_at', [Carbon::today(), Carbon::today()->addDays(30)])),
                Tables\Filters\Filter::make('due_in_3_months')
                    ->label('Due in 3 Months')
                    ->query(fn (Builder $query): Builder => $query->whereBetween('expected_checkout_at', [Carbon::today(), Carbon::today()->addMonths(3)])),
            ])
            ->actions([
                Action::make('startCheckout')
                    ->label('Start Checklist')
                    ->icon('heroicon-o-play')
                    ->color('primary')
                    ->visible(fn (RoomAllocation $record) => $record->checkout_status === 'pending')
                    ->form([
                        Forms\Components\Toggle::make('inspection_passed')
                            ->label('Inspection Passed')
                            ->default(true),
                        Forms\Components\Toggle::make('keys_collected')
                            ->label('Keys Collected')
                            ->default(false),
                        Forms\Components\Toggle::make('dues_cleared')
                            ->label('Dues Cleared')
                            ->default(false),
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->maxLength(1000),
                    ])
                    ->action(function (RoomAllocation $record, array $data): void {
                        app(CheckoutWorkflowService::class)->start($record, $data);

                        Notification::make()
                            ->success()
                            ->title('Checkout started')
                            ->body('Checklist is now in progress.')
                            ->send();
                    }),
                Action::make('completeCheckout')
                    ->label('Complete Checkout')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (RoomAllocation $record) => $record->checkout_status === 'in_progress')
                    ->form([
                        Forms\Components\Toggle::make('inspection_passed')
                            ->label('Inspection Passed')
                            ->required()
                            ->default(true),
                        Forms\Components\Toggle::make('keys_collected')
                            ->label('Keys Collected')
                            ->required()
                            ->default(true),
                        Forms\Components\Toggle::make('dues_cleared')
                            ->label('Dues Cleared')
                            ->required()
                            ->default(true),
                        Forms\Components\Textarea::make('notes')
                            ->label('Notes')
                            ->rows(3)
                            ->maxLength(1000),
                    ])
                    ->action(function (RoomAllocation $record, array $data): void {
                        app(CheckoutWorkflowService::class)->complete($record, $data);

                        Notification::make()
                            ->success()
                            ->title('Checkout completed')
                            ->body('Student has been archived and bed released.')
                            ->send();
                    }),
                Action::make('renewAllocation')
                    ->label('Renew')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->visible(fn (RoomAllocation $record) => in_array($record->checkout_status, ['pending', 'in_progress'], true))
                    ->requiresConfirmation()
                    ->modalHeading('Renew Allocation')
                    ->modalDescription(function (RoomAllocation $record): string {
                        // Check tenant-specific renewal period first, then config default
                        $tenant = $record->tenant ?? (function_exists('tenant') ? tenant() : null);
                        $months = (int) ($tenant?->settings['renewal_period_months'] ?? config('checkouts.default_period_months', 12));
                        $from = $record->expected_checkout_at
                            ? Carbon::parse($record->expected_checkout_at)
                            : Carbon::parse($record->effective_from)->addMonths($months);
                        $newDate = $from->copy()->addMonths($months)->format('d M Y');
                        $years = round($months / 12, 1);
                        return "Allocation will be renewed for {$years} year(s). New expected checkout: {$newDate}. Room/bed remains the same.";
                    })
                    ->modalSubmitActionLabel('Renew')
                    ->action(function (RoomAllocation $record): void {
                        $updated = app(CheckoutWorkflowService::class)->extend($record);

                        Notification::make()
                            ->success()
                            ->title('Allocation Renewed')
                            ->body('New expected checkout: ' . $updated->expected_checkout_at?->format('d M Y') . '.')
                            ->send();
                    }),
            ])
            ->emptyStateHeading('No checkout tasks found')
            ->emptyStateDescription('You are fully up to date with student checkouts!');
    }

    protected function getTableQuery(): Builder
    {
        $months = (int) config('checkouts.default_period_months', 18);
        $query = RoomAllocation::query()
            ->with(['student.user', 'roomBed.room', 'hostel'])
            ->where('is_active', true)
            ->where(function (Builder $query): void {
                $query->whereNotNull('expected_checkout_at')
                    ->orWhere('checkout_status', '!=', 'completed');
            })
            ->when(session('active_hostel_id'), fn (Builder $q, $hostelId) => $q->where('hostel_id', $hostelId));

        $driver = $query->getModel()->getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return $query->orderByRaw(
                "COALESCE(expected_checkout_at, datetime(effective_from, '+{$months} months')) ASC"
            );
        }

        return $query->orderByRaw(
            "COALESCE(expected_checkout_at, effective_from + interval '{$months} months') ASC"
        );
    }
}

