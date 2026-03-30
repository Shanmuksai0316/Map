<?php

namespace App\Jobs;

use App\Domain\Attendance\Models\AttendanceMark;
use App\Domain\Attendance\Models\AttendanceSession;
use App\Services\AuditLogger;
use App\Services\Metrics\Metrics;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceCloseJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function tags(): array
    {
        return ['attendance', 'gate'];
    }

    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {}

    public function handle(): void
    {
        $nowIst = Carbon::now('Asia/Kolkata');

        // Process each tenant separately to ensure RLS works correctly
        \App\Models\Tenant::query()->each(function (\App\Models\Tenant $tenant) use ($nowIst) {
            // Set tenant session variable for RLS policies
            \App\Http\Middleware\SetPostgresSessionTenant::setTenantSessionVariable($tenant->id);
            
            try {
                AttendanceSession::query()
                    ->where('tenant_id', $tenant->id)
                    ->where('status', '!=', 'closed')
                    ->get()
                    ->filter(function (AttendanceSession $session) use ($nowIst) {
                        $closeAt = $session->metadata['close_at'] ?? null;
                        if (!$closeAt) {
                            return false;
                        }
                        
                        return $nowIst->gte(Carbon::parse($closeAt));
                    })
                    ->each(function (AttendanceSession $session): void {
                        $this->closeSession($session);
                    });
            } finally {
                // Clear tenant session variable after processing
                \App\Http\Middleware\SetPostgresSessionTenant::clearTenantSessionVariable();
            }
        });
    }

    private function closeSession(AttendanceSession $session): void
    {
        DB::transaction(function () use ($session): void {
            // Find unmarked students by looking for students in the hostel who don't have attendance marks
            $markedStudentIds = $session->logs()->pluck('student_id');
            
            // Get all students in the hostel for this session
            $allStudentIds = \App\Models\RoomAllocation::query()
                ->where('tenant_id', $session->tenant_id)
                ->where('is_active', true)
                ->whereHas('roomBed', function ($query) use ($session) {
                    $query->whereHas('room', function ($q) use ($session) {
                        $q->where('hostel_id', $session->hostel_id);
                    });
                })
                ->pluck('student_id');

            $unmarkedStudentIds = $allStudentIds->diff($markedStudentIds);

            foreach ($unmarkedStudentIds as $studentId) {
                $this->auditLogger->log('attendance.unmarked_on_close', $session, [
                    'student_id' => $studentId,
                ]);
            }

            // Close the session
            $session->forceFill(['status' => 'closed'])->save();
            $session->recomputeCounts();
            
            // Send metrics
            Metrics::count('AttendanceClosed', 1, [
                'tenant_id' => $session->tenant_id,
                'hostel_id' => $session->hostel_id,
                'unmarked_count' => $unmarkedStudentIds->count(),
            ]);
        });
    }
}
