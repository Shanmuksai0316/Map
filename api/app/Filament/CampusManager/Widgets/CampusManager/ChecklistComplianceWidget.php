<?php

namespace App\Filament\CampusManager\Widgets\CampusManager;

use App\Domain\Checklists\Models\ChecklistInstance;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ChecklistComplianceWidget extends Widget
{
    protected static string $view = 'filament.campus-manager.widgets.checklist-compliance';

    protected int|string|array $columnSpan = [
        'md' => 2,
    ];

    protected function getViewData(): array
    {
        try {
            // ChecklistInstance doesn't use TenantScoped, so we need to filter manually
            try {
                $tenantId = tenant('id') ?? auth()->user()?->tenant_id;
            } catch (\Exception $e) {
                $tenantId = auth()->user()?->tenant_id;
            }
            $today = Carbon::today();

            $todayQuery = ChecklistInstance::query()->whereDate('date', $today);
            if ($tenantId) {
                $todayQuery->where('tenant_id', $tenantId);
            }
            $total = (clone $todayQuery)->count();
            $approved = (clone $todayQuery)->where('review_status', 'Approved')->count();
            $submitted = (clone $todayQuery)->where('status', 'Submitted')->count();
            $pending = (clone $todayQuery)->where('status', 'Pending')->count();
            $complianceRate = $total > 0 ? round(($approved / $total) * 100) : 0;

            $overdueQuery = ChecklistInstance::query()
                ->where('status', 'Pending')
                ->whereDate('date', '<', $today);
            if ($tenantId) {
                $overdueQuery->where('tenant_id', $tenantId);
            }
            $overdue = $overdueQuery->count();

            $roleComplianceQuery = ChecklistInstance::query()
                ->select('role', DB::raw('AVG(CASE WHEN review_status = \'Approved\' THEN 1 ELSE 0 END) as rate'))
                ->whereDate('date', '>=', $today->copy()->subDays(7));
            if ($tenantId) {
                $roleComplianceQuery->where('tenant_id', $tenantId);
            }
            $roleCompliance = $roleComplianceQuery
                ->groupBy('role')
                ->pluck('rate', 'role')
                ->map(fn ($rate) => round($rate * 100))
                ->toArray();

            return [
                'total' => $total,
                'approved' => $approved,
                'submitted' => $submitted,
                'pending' => $pending,
                'overdue' => $overdue,
                'complianceRate' => $complianceRate,
                'roleCompliance' => $roleCompliance,
            ];
        } catch (\Exception $e) {
            \Log::error('ChecklistComplianceWidget error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'total' => 0,
                'approved' => 0,
                'submitted' => 0,
                'pending' => 0,
                'overdue' => 0,
                'complianceRate' => 0,
                'roleCompliance' => [],
            ];
        }
    }
}

