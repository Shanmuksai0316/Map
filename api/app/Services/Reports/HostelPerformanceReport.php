<?php

namespace App\Services\Reports;

use App\Models\Hostel;
use App\Models\RoomAllocation;
use App\Models\OutPass;
use App\Models\Ticket;
use App\Support\HostelScope;
use Carbon\Carbon;

class HostelPerformanceReport
{
    public static function generate(array $params): \Generator
    {
        $tenantId = auth()->user()->tenant_id;
        $fromDate = Carbon::parse($params['from_date'])->startOfDay();
        $toDate = Carbon::parse($params['to_date'])->endOfDay();
        $hostelId = $params['hostel_id'] ?? null;
        
        // Get hostel IDs to filter by
        $hostelIds = $hostelId ? [$hostelId] : HostelScope::idsFor(auth()->user());
        
        $hostels = Hostel::where('tenant_id', $tenantId)
            ->when($hostelId, fn($q) => $q->where('id', $hostelId))
            ->get();
            
        foreach ($hostels as $hostel) {
            // Calculate metrics for this hostel
            $totalBeds = $hostel->rooms()->sum('bed_count');
            $occupiedBeds = RoomAllocation::where('hostel_id', $hostel->id)
                ->where('is_active', true)
                ->count();
            $utilization = $totalBeds > 0 ? round(($occupiedBeds / $totalBeds) * 100, 1) : 0;
            
            $outPassesCount = OutPass::where('hostel_id', $hostel->id)
                ->whereBetween('created_at', [$fromDate, $toDate])
                ->count();
                
            $ticketsCount = Ticket::where('hostel_id', $hostel->id)
                ->whereBetween('created_at', [$fromDate, $toDate])
                ->count();
                
            // PostgreSQL: EXTRACT(EPOCH FROM (resolved_at - created_at))/3600 converts difference to hours
            $avgTicketResolution = Ticket::where('hostel_id', $hostel->id)
                ->whereBetween('created_at', [$fromDate, $toDate])
                ->whereNotNull('resolved_at')
                ->selectRaw('AVG(EXTRACT(EPOCH FROM (resolved_at - created_at))/3600) as avg_hours')
                ->value('avg_hours');
                
            yield [
                'Hostel Name' => $hostel->name,
                'Total Beds' => $totalBeds,
                'Occupied Beds' => $occupiedBeds,
                'Utilization %' => $utilization,
                'OutPasses' => $outPassesCount,
                'Tickets' => $ticketsCount,
                'Avg Resolution (Hours)' => $avgTicketResolution ? round($avgTicketResolution, 1) : 'N/A',
                'Period' => $fromDate->format('Y-m-d') . ' to ' . $toDate->format('Y-m-d'),
            ];
        }
    }
}
