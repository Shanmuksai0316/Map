<?php

namespace App\Services\Reports;

use App\Domain\Attendance\Models\AttendanceSession;
use App\Models\Hostel;
use Carbon\Carbon;

class AttendanceComplianceReport
{
    public static function generate(array $params): \Generator
    {
        $tenantId = auth()->user()->tenant_id;
        $fromDate = Carbon::parse($params['from_date'])->startOfDay();
        $toDate = Carbon::parse($params['to_date'])->endOfDay();
        $hostelId = $params['hostel_id'] ?? null;

        $hostels = Hostel::where('tenant_id', $tenantId)
            ->when($hostelId, fn ($q) => $q->where('id', $hostelId))
            ->get();

        foreach ($hostels as $hostel) {
            // Use database aggregation instead of loading all records into memory
            $query = AttendanceSession::where('hostel_id', $hostel->id)
                ->whereBetween('created_at', [$fromDate, $toDate]);

            $totalSessions = $query->count();
            $closedSessions = (clone $query)->whereNotNull('closed_at')->count();
            $complianceRate = $totalSessions > 0 ? round(($closedSessions / $totalSessions) * 100, 1) : 0;

            // Calculate average closure time using DB aggregation
            // Using selectRaw to calculate hours difference in database
            // PostgreSQL: EXTRACT(EPOCH FROM (closed_at - created_at))/3600 converts difference to hours
            $avgClosureTimeResult = (clone $query)
                ->whereNotNull('closed_at')
                ->selectRaw('AVG(EXTRACT(EPOCH FROM (closed_at - created_at))/3600) as avg_hours')
                ->first();

            $avgClosureTime = $avgClosureTimeResult?->avg_hours
                ? round($avgClosureTimeResult->avg_hours, 1)
                : 'N/A';

            yield [
                'Hostel Name' => $hostel->name,
                'Total Sessions' => $totalSessions,
                'Closed Sessions' => $closedSessions,
                'Compliance Rate %' => $complianceRate,
                'Avg Closure Time (Hours)' => $avgClosureTime,
                'Period' => $fromDate->format('Y-m-d').' to '.$toDate->format('Y-m-d'),
            ];
        }
    }
}
