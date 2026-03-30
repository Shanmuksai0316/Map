<?php

namespace App\Services\Reports;

use App\Models\Ticket;
use App\Models\Hostel;
use App\Support\HostelScope;
use Carbon\Carbon;

class TicketsSlaAgingReport
{
    public static function generate(array $params): \Generator
    {
        $tenantId = auth()->user()->tenant_id;
        $fromDate = Carbon::parse($params['from_date'])->startOfDay();
        $toDate = Carbon::parse($params['to_date'])->endOfDay();
        $hostelId = $params['hostel_id'] ?? null;
        
        $tickets = Ticket::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->when($hostelId, fn($q) => $q->where('hostel_id', $hostelId))
            ->with('hostel')
            ->get();
            
        foreach ($tickets as $ticket) {
            $ageInHours = $ticket->created_at->diffInHours(now());
            $slaStatus = 'On Time';
            
            if ($ticket->sla_deadline && now()->gt($ticket->sla_deadline)) {
                $slaStatus = 'Breached';
            } elseif ($ticket->sla_deadline && now()->diffInHours($ticket->sla_deadline) < 24) {
                $slaStatus = 'At Risk';
            }
            
            yield [
                'Ticket ID' => $ticket->id,
                'Hostel' => $ticket->hostel->name,
                'Title' => $ticket->title,
                'Priority' => $ticket->priority,
                'Status' => $ticket->status,
                'Age (Hours)' => $ageInHours,
                'SLA Status' => $slaStatus,
                'Created' => $ticket->created_at->format('Y-m-d H:i'),
                'SLA Deadline' => $ticket->sla_deadline ? $ticket->sla_deadline->format('Y-m-d H:i') : 'N/A',
            ];
        }
    }
}
