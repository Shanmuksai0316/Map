<?php

namespace App\Console\Commands;

use App\Models\AttendanceSession;
use App\Models\Incident;
use Illuminate\Console\Command;

class CreateMissedAttendanceIncidentsCommand extends Command
{
    protected $signature = 'app:create-missed-attendance-incidents';

    protected $description = 'Create incidents for closed attendance sessions with unmarked students';

    public function handle(): int
    {
        $this->info('Checking for missed attendance...');

        $incidentsCreated = 0;

        // Find recently closed sessions (within last hour) with unmarked students
        $sessions = AttendanceSession::where('status', 'Closed')
            ->where('updated_at', '>=', now()->subHour())
            ->get();

        foreach ($sessions as $session) {
            // Count unmarked students for this session
            $unmarkedCount = \App\Models\AttendanceLog::where('attendance_session_id', $session->id)
                ->where('status', 'Unmarked')
                ->count();

            if ($unmarkedCount > 0) {
                // Check if incident already exists for this session
                $existingIncident = Incident::where('type', Incident::TYPE_MISSED_ATTENDANCE)
                    ->where('hostel_id', $session->hostel_id)
                    ->where('metadata->attendance_session_id', $session->id)
                    ->exists();

                if (!$existingIncident) {
                    // Create incident
                    Incident::create([
                        'tenant_id' => $session->tenant_id,
                        'hostel_id' => $session->hostel_id,
                        'type' => Incident::TYPE_MISSED_ATTENDANCE,
                        'note' => "Attendance session on {$session->session_date->format('Y-m-d')} has {$unmarkedCount} unmarked students",
                        'status' => 'Open',
                        'opened_by' => 1, // System user
                        'opened_at' => now(),
                        'metadata' => [
                            'attendance_session_id' => $session->id,
                            'unmarked_count' => $unmarkedCount,
                            'session_date' => $session->session_date->format('Y-m-d'),
                        ],
                    ]);

                    $incidentsCreated++;
                    $this->info("Created incident for session {$session->id} with {$unmarkedCount} unmarked students");
                }
            }
        }

        $this->info("Created {$incidentsCreated} missed attendance incidents");

        return Command::SUCCESS;
    }
}

