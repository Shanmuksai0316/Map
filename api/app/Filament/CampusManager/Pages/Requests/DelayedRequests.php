<?php

namespace App\Filament\CampusManager\Pages\Requests;

use App\Domain\Tickets\Models\Ticket;
use App\Models\LaundryRequest;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Collection;

/**
 * Delayed Requests page -- shows overdue housekeeping/repair tickets and delayed laundry requests.
 *
 * All queries are scoped to the current tenant via tenant_id (resolved from subdomain or user).
 * The navigation badge displays a count of delayed items so managers can see at a glance
 * how many requests need attention. Badge color is 'danger' to draw attention.
 */
class DelayedRequests extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Delayed Requests';

    protected static ?string $navigationGroup = 'Requests';

    protected static ?int $navigationSort = 0;

    protected static string $view = 'filament.campus-manager.pages.requests.delayed-requests';

    public string $laundrySearch = '';

    public function getHeading(): string
    {
        return 'Delayed Requests';
    }

    public static function getNavigationBadge(): ?string
    {
        try {
            $tenantId = function_exists('tenant') && tenant() ? tenant()->id : auth()->user()?->tenant_id;
            if (! $tenantId) {
                return null;
            }
            $tickets = Ticket::query()
                ->where('tenant_id', $tenantId)
                ->delayed()
                ->count();
            $laundry = 0;
            if (\Schema::hasColumn((new LaundryRequest)->getTable(), 'tenant_id')) {
                $laundry = LaundryRequest::query()->where('tenant_id', $tenantId)->delayed()->count();
            }
            $total = $tickets + $laundry;
            return $total > 0 ? (string) $total : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public function getDelayedLaundryRequests(): Collection
    {
        try {
            $tenantId = function_exists('tenant') && tenant() ? tenant()->id : auth()->user()?->tenant_id;
            if (! $tenantId) {
                return collect();
            }

            $search = trim($this->laundrySearch);
            $numericSearch = preg_replace('/[^0-9]/', '', $search);

            $query = LaundryRequest::query()->delayed()->with(['student.user', 'student.roomAllocations.roomBed.room']);
            if (\Schema::hasColumn((new LaundryRequest)->getTable(), 'tenant_id')) {
                $query->where('tenant_id', $tenantId);
            }

            if ($search !== '') {
                $query->where(function (Builder $q) use ($search, $numericSearch): void {
                    if ($numericSearch !== '') {
                        $q->where('id', 'like', '%' . $numericSearch . '%')
                            ->orWhere('status', 'ilike', '%' . $search . '%');
                    } else {
                        $q->where('status', 'ilike', '%' . $search . '%');
                    }

                    $q->orWhereHas('student.user', fn (Builder $studentUserQuery) => $studentUserQuery->where('name', 'ilike', '%' . $search . '%'))
                        ->orWhereHas('student', fn (Builder $studentQuery) => $studentQuery->where('full_name', 'ilike', '%' . $search . '%'))
                        ->orWhereHas('student.roomAllocations.roomBed.room', fn (Builder $roomQuery) => $roomQuery->where('number', 'ilike', '%' . $search . '%'));
                });
            }

            return $query->orderByDesc('requested_at')->get();
        } catch (\Throwable $e) {
            return collect();
        }
    }

    public function table(Table $table): Table
    {
        $tenantId = function_exists('tenant') && tenant() ? tenant()->id : auth()->user()?->tenant_id;

        return $table
            ->query(
                Ticket::query()
                    ->whereIn('category', ['housekeeping', 'maintenance', 'repair_maintenance'])
                    ->delayed()
                    ->when($tenantId, fn (Builder $q) => $q->where('tenant_id', $tenantId))
                    ->with(['reporterStudent.user', 'reporterStudent.roomAllocations.roomBed.room'])
            )
            ->heading(new HtmlString('<span class="whitespace-nowrap">Delayed housekeeping and repair requests</span>'))
            ->columns([
                Tables\Columns\TextColumn::make('request_id')
                    ->label('Request ID')
                    ->getStateUsing(fn (Ticket $record) => ($record->category === 'housekeeping' ? 'HK-' : 'RM-') . str_pad((string) $record->id, 4, '0', STR_PAD_LEFT))
                    ->searchable(query: fn (Builder $query, string $search) =>
                        $query->where('id', 'like', '%' . preg_replace('/[^0-9]/', '', $search) . '%')
                    ),
                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->getStateUsing(fn (Ticket $record) => $record->category === 'housekeeping' ? 'Housekeeping' : 'Repair & Maintenance'),
                Tables\Columns\TextColumn::make('student_room')
                    ->label('Student – Room')
                    ->getStateUsing(function (Ticket $record) {
                        $name = $record->reporter_name ?? 'Unknown';
                        $room = $record->reporterStudent?->roomAllocations?->first()?->roomBed?->room?->number ?? '—';
                        return "{$name} – {$room}";
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'open' => 'Pending',
                        'in_progress' => 'In Progress',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state) => match ($state) {
                        'open' => 'warning',
                        'in_progress' => 'info',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No delayed housekeeping or repair requests')
            ->emptyStateDescription('Aim to keep this list at zero.')
            ->striped();
    }
}
