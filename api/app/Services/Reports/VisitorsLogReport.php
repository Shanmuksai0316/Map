<?php

namespace App\Services\Reports;

use App\Models\GuestVisit;
use App\Models\Hostel;
use App\Support\HostelScope;
use Carbon\Carbon;

class VisitorsLogReport
{
    public static function generate(array $params): \Generator
    {
        $tenantId = auth()->user()->tenant_id;
        $fromDate = Carbon::parse($params['from_date'])->startOfDay();
        $toDate = Carbon::parse($params['to_date'])->endOfDay();
        $hostelId = $params['hostel_id'] ?? null;
        
        $visits = GuestVisit::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->when($hostelId, fn($q) => $q->where('hostel_id', $hostelId))
            ->with(['hostel', 'student.user'])
            ->get();
            
        foreach ($visits as $visit) {
            $duration = null;
            if ($visit->check_in_at && $visit->check_out_at) {
                $duration = $visit->check_in_at->diffInHours($visit->check_out_at);
            }
            
            yield [
                'Visit ID' => $visit->id,
                'Guest Name' => $visit->guest_name,
                'Guest Phone' => $visit->guest_phone,
                'Student Name' => $visit->student->user->name,
                'Student UID' => $visit->student->student_uid,
                'Hostel' => $visit->hostel->name,
                'Purpose' => $visit->purpose,
                'Check In' => $visit->check_in_at ? $visit->check_in_at->format('Y-m-d H:i') : 'N/A',
                'Check Out' => $visit->check_out_at ? $visit->check_out_at->format('Y-m-d H:i') : 'Not Checked Out',
                'Duration (Hours)' => $duration ? round($duration, 1) : 'N/A',
                'Status' => $visit->status,
                'Created' => $visit->created_at->format('Y-m-d H:i'),
            ];
        }
    }
}
