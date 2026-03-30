<?php

namespace App\Services\Attendance;

use App\Domain\Attendance\Models\AttendanceSession;
use App\Models\Domain\OutPass\OutPass;
use Illuminate\Support\Collection;

class LeaveDeriver
{
    public function leaveStudentIds(AttendanceSession $session): Collection
    {
        // Check if session has valid metadata
        if (!isset($session->metadata['open_at']) || !isset($session->metadata['close_at'])) {
            return collect(); // Return empty collection if no valid session window
        }
        
        $sessionOpenAt = \Carbon\Carbon::parse($session->metadata['open_at']);
        $sessionCloseAt = \Carbon\Carbon::parse($session->metadata['close_at']);

        $studentIds = OutPass::query()
            ->where('tenant_id', $session->tenant_id)
            ->where('status', 'approved')
            ->where(function ($query) use ($sessionOpenAt, $sessionCloseAt) {
                // Use valid_until instead of to_date since that's what exists in the schema
                $query->where('requested_at', '<=', $sessionCloseAt)
                    ->where('valid_until', '>=', $sessionOpenAt);
            })
            ->pluck('student_id') // This is student_id in out_passes table
            ->unique();

        // Convert Student IDs to User IDs for API compatibility
        return \App\Models\Student::query()
            ->whereIn('id', $studentIds)
            ->pluck('user_id')
            ->unique();
    }
}
