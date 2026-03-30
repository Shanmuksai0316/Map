<?php

namespace App\Filament\Rector\Pages;

use App\Domain\Leaves\Models\LeaveHistory;
use App\Models\Domain\OutPass\OutPass;
use App\Models\Domain\OutPass\OutPassHistory;
use Filament\Forms;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;

class ApprovalHistory extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $navigationIcon = null;

    protected static ?string $navigationLabel = 'Approval History';

    protected static ?string $navigationGroup = 'Approvals';

    protected static ?int $navigationSort = 4;

    protected static string $view = 'filament.rector.pages.approval-history';

    public function table(Table $table): Table
    {
        $tenantId = auth()->user()?->tenant_id;

        if (!$tenantId) {
            return $table->query(OutPass::whereRaw('1 = 0'));
        }

        return $table
            ->query(function () use ($tenantId) {
                // Query OutPass histories with relationships
                return OutPassHistory::query()
                    ->with(['outPass.student.user', 'outPass.hostel', 'actor'])
                    ->whereHas('outPass', function ($q) use ($tenantId) {
                        $q->where('tenant_id', $tenantId)
                            ->whereIn('status', ['approved', 'declined']);
                    })
                    ->whereIn('to_status', ['approved', 'declined'])
                    ->orderBy('changed_at', 'desc');
            })
            ->columns([
                BadgeColumn::make('type')
                    ->label('Type')
                    ->getStateUsing(fn () => 'Out-Pass')
                    ->colors([
                        'primary' => 'Out-Pass',
                    ]),
                TextColumn::make('outPass.unique_id')
                    ->label('Request ID')
                    ->searchable(),
                TextColumn::make('outPass.student.user.name')
                    ->label('Student')
                    ->searchable()
                    ->default('Unknown'),
                TextColumn::make('outPass.hostel.name')
                    ->label('Hostel')
                    ->default('--'),
                BadgeColumn::make('to_status')
                    ->label('Decision')
                    ->colors([
                        'success' => 'approved',
                        'danger' => 'declined',
                    ])
                    ,
                TextColumn::make('changed_at')
                    ->label('Decided At')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('actor.name')
                    ->label('Decided By')
                    ->default('--'),
                TextColumn::make('timeline_label')
                    ->label('Action')
                    ->limit(30),
                TextColumn::make('note')
                    ->label('Note')
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        if (!$state || strlen($state) <= 50) {
                            return null;
                        }
                        return $state;
                    }),
            ])
            ->filters([
                SelectFilter::make('to_status')
                    ->label('Decision')
                    ->options([
                        'approved' => 'Approved',
                        'declined' => 'Declined',
                    ]),
                Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from_date')
                            ->label('From Date'),
                        Forms\Components\DatePicker::make('to_date')
                            ->label('To Date'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from_date'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('changed_at', '>=', $date))
                            ->when($data['to_date'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('changed_at', '<=', $date));
                    }),
            ])
            ->defaultSort('changed_at', 'desc')
            ->paginated([10, 25, 50, 100])
            ->poll('30s');
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        return $user && $user->hasRole('Rector');
    }
}
