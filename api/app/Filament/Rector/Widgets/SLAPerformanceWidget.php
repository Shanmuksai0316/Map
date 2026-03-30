<?php

namespace App\Filament\Rector\Widgets;

use App\Models\Domain\OutPass\OutPass;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SLAPerformanceWidget extends ChartWidget
{
    protected static ?string $heading = 'SLA Performance (Last 7 Days)';

    protected int|string|array $columnSpan = [
        'md' => 1,
    ];

    protected static ?int $sort = 3;

    protected function getData(): array
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

        // Get decisions from the last 7 days
        $startDate = now()->subDays(7)->startOfDay();
        $endDate = now()->endOfDay();

        // Out-Pass decisions
        $outPassQuery = OutPass::where('tenant_id', $tenantId)
            ->whereIn('status', ['approved', 'declined'])
            ->whereBetween('decided_at', [$startDate, $endDate]);

        if (!empty($assignedHostelIds)) {
            $outPassQuery->whereIn('hostel_id', $assignedHostelIds);
        }

        $outPasses = $outPassQuery->get();

        $withinSLA = 0;
        $breachedSLA = 0;
        $outPassSLAHours = 2;

        foreach ($outPasses as $outPass) {
            if ($outPass->requested_at && $outPass->decided_at) {
                $hoursToDecision = $outPass->requested_at->diffInHours($outPass->decided_at);
                if ($hoursToDecision <= $outPassSLAHours) {
                    $withinSLA++;
                } else {
                    $breachedSLA++;
                }
            }
        }

        // Get Leave decisions
        $leaveQuery = DB::table('leaves')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['approved', 'rejected'])
            ->whereBetween('approved_at', [$startDate, $endDate]);

        if (!empty($assignedHostelIds)) {
            $leaveQuery->whereIn('hostel_id', $assignedHostelIds);
        }

        $leaves = $leaveQuery->get();
        $leaveSLAHours = 4;

        foreach ($leaves as $leave) {
            if ($leave->submitted_at && $leave->approved_at) {
                $submittedAt = \Carbon\Carbon::parse($leave->submitted_at);
                $approvedAt = \Carbon\Carbon::parse($leave->approved_at);
                $hoursToDecision = $submittedAt->diffInHours($approvedAt);
                if ($hoursToDecision <= $leaveSLAHours) {
                    $withinSLA++;
                } else {
                    $breachedSLA++;
                }
            }
        }

        return [
            'datasets' => [
                [
                    'data' => [$withinSLA, $breachedSLA],
                    'backgroundColor' => ['#22c55e', '#ef4444'],
                    'borderWidth' => 0,
                ],
            ],
            'labels' => ['Within SLA', 'Breached SLA'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
            ],
        ];
    }
}

