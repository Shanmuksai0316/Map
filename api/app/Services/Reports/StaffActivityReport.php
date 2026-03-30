<?php

namespace App\Services\Reports;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StaffActivityReport
{
    public static function generate(array $params): \Generator
    {
        $fromDate = Carbon::parse($params['from_date'] ?? now()->subDays(7))->startOfDay();
        $toDate = Carbon::parse($params['to_date'] ?? now())->endOfDay();

        $staffQuery = DB::table('users')
            ->where('kind', '!=', 'student')
            ->whereBetween('created_at', [$fromDate, $toDate]);

        if (!empty($params['tenant_id'])) {
            $staffQuery->where('tenant_id', $params['tenant_id']);
        }

        $staff = $staffQuery
            ->select('tenant_id', DB::raw('count(*) as staff_count'))
            ->groupBy('tenant_id')
            ->get();

        foreach ($staff as $row) {
            $tenantId = $row->tenant_id;

            $checklistsCompleted = 0;
            if (Schema::hasTable('checklist_instances')) {
                $checklistsCompleted = DB::table('checklist_instances')
                    ->whereBetween('completed_at', [$fromDate, $toDate])
                    ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                    ->count();
            }

            $ticketsResolved = 0;
            if (Schema::hasTable('tickets')) {
                $ticketsResolved = DB::table('tickets')
                    ->whereBetween('updated_at', [$fromDate, $toDate])
                    ->where('status', 'resolved')
                    ->when($tenantId, fn ($q) => $q->where('tenant_id', $tenantId))
                    ->count();
            }

            yield [
                'Tenant ID' => $tenantId ?? 'N/A',
                'Staff Count' => $row->staff_count,
                'Checklists Completed' => $checklistsCompleted,
                'Tickets Resolved' => $ticketsResolved,
                'Period' => $fromDate->format('Y-m-d') . ' to ' . $toDate->format('Y-m-d'),
            ];
        }
    }
}

