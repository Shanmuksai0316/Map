<?php

namespace App\Filament\Rector\Widgets;

use App\Enums\OutPassStatus;
use App\Models\Domain\OutPass\OutPass;
use App\Models\CombinedLeaveRequest;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PendingApprovalsWidget extends BaseWidget
{
    protected static ?string $heading = 'Urgent Pending Approvals';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public function table(Table $table): Table
    {
        $user = Auth::user();
        $tenantId = $user?->tenant_id;

        // Get assigned hostel IDs
        $assignedHostelIds = DB::table('staff_assignments')
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->whereNull('revoked_at')
            ->whereNotNull('hostel_id')
            ->pluck('hostel_id')
            ->toArray();

        // Use CombinedLeaveRequest for leaves (includes both Leave and SickLeave)
        return $table
            ->query(function () use ($tenantId, $assignedHostelIds) {
                // Get pending leaves with relationships.
                // We intentionally do NOT filter by tenant_id here so that rectors
                // can see pending leaves even if there is a mismatch between
                // mobile tenant codes and Rector tenant codes (e.g. MAP-PPCU vs PPCU).
                $leavesQuery = CombinedLeaveRequest::query()
                    ->where('status', 'pending')
                    ->with(['student.user', 'hostel']);
                
                if (!empty($assignedHostelIds)) {
                    $leavesQuery->whereIn('hostel_id', $assignedHostelIds);
                }
                
                // Get pending out-passes
                $outPassQuery = OutPass::query()
                    ->with(['student.user', 'hostel'])
                    ->where('tenant_id', $tenantId)
                    ->where('status', OutPassStatus::PENDING);
                
                if (!empty($assignedHostelIds)) {
                    $outPassQuery->whereIn('hostel_id', $assignedHostelIds);
                }
                
                // For now, show leaves (most common). TODO: Create unified pending requests model
                return $leavesQuery->orderBy('submitted_at', 'asc')->limit(10);
            })
            ->columns([
                TextColumn::make('unique_id')
                    ->label('ID')
                    ->size('sm'),
                TextColumn::make('student.user.name')
                    ->label('Student')
                    ->getStateUsing(function (CombinedLeaveRequest $record) {
                        return $record->student?->user?->name ?? '--';
                    })
                    ->size('sm'),
                TextColumn::make('hostel.name')
                    ->label('Hostel')
                    ->getStateUsing(function (CombinedLeaveRequest $record) {
                        return $record->hostel?->name ?? '--';
                    })
                    ->size('sm'),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state === 'sick_leave' ? 'Sick Leave' : ($state === 'leave' ? 'Leave' : ucfirst($state)))
                    ->color(fn (string $state): string => match ($state) {
                        'leave' => 'primary',
                        'sick_leave' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('title')
                    ->label('Reason')
                    ->limit(30)
                    ->size('sm'),
                TextColumn::make('sla_status')
                    ->label('SLA')
                    ->getStateUsing(function (CombinedLeaveRequest $record) {
                        if (!$record->submitted_at) {
                            return '--';
                        }
                        $hours = now()->diffInHours($record->submitted_at);
                        $slaHours = 24; // 24 hour SLA for leaves
                        $remaining = $slaHours - $hours;

                        if ($remaining <= 0) {
                            return '🔴 Overdue';
                        } elseif ($remaining <= 4) {
                            return '🟡 Due soon';
                        }
                        return '🟢 ' . round($remaining, 1) . 'h left';
                    }),
            ])
            ->paginated(false)
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Review')
                    ->icon('heroicon-o-eye')
                    ->url(fn (CombinedLeaveRequest $record): string => route('filament.rector.resources.leaves.view', $record)),
            ])
            ->emptyStateHeading('No Pending Approvals')
            ->emptyStateDescription('All requests have been processed!')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
