<?php

namespace App\Jobs\Attendance;

use App\Domain\Attendance\Models\AttendanceSessionV2;
use App\Models\AttendanceLog;
use App\Models\Incident;
use App\Models\RoomBed;
use App\Services\AuditLogger;
use App\Services\Notifications\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class CloseSessionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {}

    public function handle(SmsService $smsService): void
    {
        $now = now('Asia/Kolkata');
        
        // Process each tenant separately to ensure RLS works correctly
        \App\Models\Tenant::query()->each(function (\App\Models\Tenant $tenant) use ($now) {
            // Set tenant session variable for RLS policies
            \App\Http\Middleware\SetPostgresSessionTenant::setTenantSessionVariable($tenant->id);
            
            try {
                // Find sessions that should be closed (active status and window has ended)
                $sessionsToClose = AttendanceSessionV2::where('tenant_id', $tenant->id)
                    ->where('status', 'active')
                    ->where('session_date', $now->toDateString())
                    ->get();
                    
                foreach ($sessionsToClose as $session) {
                    $windowEnd = Carbon::parse($session->metadata['window']['end'] ?? $session->scheduled_at->addHours(2));
                    
                    // If current time is past window end, close the session
                    if ($now->gte($windowEnd)) {
                        $session->update(['status' => 'closed']);
                        
                        // Create incidents for unmarked students and notify parents
                        $this->createMissedAttendanceIncidents($session, $smsService);
                    }
                }
            } finally {
                // Clear tenant session variable after processing
                \App\Http\Middleware\SetPostgresSessionTenant::clearTenantSessionVariable();
            }
        });
    }
    
    private function createMissedAttendanceIncidents(AttendanceSessionV2 $session, SmsService $smsService): void
    {
        // Get all students in the hostel
        $roomBeds = RoomBed::whereHas('room', function ($query) use ($session) {
            $query->where('hostel_id', $session->hostel_id);
        })->with('student')->get();
        
        foreach ($roomBeds as $roomBed) {
            if (!$roomBed->student) continue;
            
            $studentId = $roomBed->student->id;
            
            // Check if student has attendance marked for this session
            $hasAttendance = AttendanceLog::where('session_id', $session->id)
                ->where('student_id', $studentId)
                ->exists();
                
            // If no attendance marked, create incident (idempotent) and send attendance alert SMS
            if (!$hasAttendance) {
                $incident = Incident::firstOrCreate(
                    [
                        'tenant_id' => $session->tenant_id,
                        'type' => 'missed_attendance',
                        'session_id' => $session->id,
                        'student_id' => $studentId,
                    ],
                    [
                        'severity' => 'medium',
                        'labels' => [
                            'hostel_id' => $session->hostel_id,
                            'date' => $session->session_date,
                        ],
                    ]
                );
                
                // Log audit event
                $this->auditLogger->info('ATTENDANCE_MISSED', [
                    'session_id' => $session->id,
                    'student_id' => $studentId,
                    'hostel_id' => $session->hostel_id,
                    'date' => $session->session_date,
                ]);

                // Only send SMS when incident is newly created to avoid duplicates
                if ($incident->wasRecentlyCreated && $roomBed->student) {
                    $student = $roomBed->student;
                    $studentName = $student->full_name ?? $student->user->name ?? 'Student';

                    $parentPhone = $student->father_mobile_number
                        ?: $student->mother_mobile_number
                        ?: null;

                    if ($parentPhone) {
                        $hostelName = $roomBed->hostel?->name ?? 'Hostel';
                        $date = $session->session_date?->format('d-M-Y') ?? $session->session_date;

                        $message = "Attendance Alert: {$studentName} marked Absent on {$date}. Hostel: {$hostelName}.Team OMAP Services";

                        $smsService->send(
                            $parentPhone,
                            $message,
                            (string) $session->tenant_id,
                            'attendance_alert',
                            [
                                'related_type' => 'attendance_missed',
                                'related_id' => (string) $incident->id,
                                'student_id' => $studentId,
                                'session_id' => $session->id,
                            ]
                        );
                    }
                }
            }
        }
    }
}
