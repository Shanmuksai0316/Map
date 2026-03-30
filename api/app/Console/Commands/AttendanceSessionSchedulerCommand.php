<?php

namespace App\Console\Commands;

use App\Models\AttendanceSession;
use App\Models\Hostel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AttendanceSessionSchedulerCommand extends Command
{
    protected $signature = 'app:attendance-session-scheduler';

    protected $description = 'Auto-open and auto-close attendance sessions based on hostel curfew times';

    public function handle(): int
    {
        $this->info('Running attendance session scheduler...');

        $openedCount = 0;
        $closedCount = 0;

        // Get all hostels with curfew times
        $hostels = Hostel::whereNotNull('curfew_time')->get();

        foreach ($hostels as $hostel) {
            $curfewTime = $hostel->curfew_time;
            $today = now()->toDateString();
            
            // Calculate open and close times
            $openTime = now()->setTimeFromTimeString($curfewTime)->subHour(); // curfew - 1h
            $closeTime = now()->setTimeFromTimeString($curfewTime)->addHours(2); // curfew + 2h
            
            // Check if we should open a session (current time >= open time AND < close time)
            if (now()->gte($openTime) && now()->lt($closeTime)) {
                // Check if session exists for today
                $session = AttendanceSession::where('hostel_id', $hostel->id)
                    ->where('session_date', $today)
                    ->first();

                if (!$session) {
                    // Create and open session
                    AttendanceSession::create([
                        'tenant_id' => $hostel->tenant_id,
                        'hostel_id' => $hostel->id,
                        'session_date' => $today,
                        'open_time' => $openTime->format('H:i:s'),
                        'close_time' => $closeTime->format('H:i:s'),
                        'status' => 'Open',
                    ]);
                    $openedCount++;
                    $this->info("Opened session for hostel {$hostel->name}");
                }
            }

            // Check if we should close a session (current time >= close time)
            if (now()->gte($closeTime)) {
                $closedSessions = AttendanceSession::where('hostel_id', $hostel->id)
                    ->where('session_date', $today)
                    ->where('status', 'Open')
                    ->update([
                        'status' => 'Closed',
                        'updated_at' => now(),
                    ]);
                
                if ($closedSessions > 0) {
                    $closedCount += $closedSessions;
                    $this->info("Closed session for hostel {$hostel->name}");
                }
            }
        }

        $this->info("Opened {$openedCount} sessions, Closed {$closedCount} sessions");

        return Command::SUCCESS;
    }
}

