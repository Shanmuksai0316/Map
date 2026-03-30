<?php

namespace App\Console\Commands;

use App\Models\SportsEnrollment;
use App\Models\SportsEvent;
use Illuminate\Console\Command;

class MarkSportsNoShowCommand extends Command
{
    protected $signature = 'app:mark-sports-no-show';

    protected $description = 'Mark sports bookings as no-show if not checked in within 15 minutes of start time';

    public function handle(): int
    {
        $this->info('Checking for sports no-shows...');

        $noShowCount = 0;

        // Find events that started more than 15 minutes ago
        $events = SportsEvent::where('start_at', '<', now()->subMinutes(15))
            ->where('start_at', '>=', now()->subHours(2)) // Only check recent events (last 2 hours)
            ->get();

        foreach ($events as $event) {
            // Find enrollments that are still Active (not checked in)
            $enrollments = SportsEnrollment::where('sports_event_id', $event->id)
                ->where('status', 'Active')
                ->whereNull('attended_at')
                ->get();

            foreach ($enrollments as $enrollment) {
                // Mark as no-show
                $enrollment->update([
                    'status' => 'NoShow',
                    'notes' => 'Auto-marked as no-show after 15 minutes',
                    'metadata' => array_merge($enrollment->metadata ?? [], [
                        'no_show_marked_at' => now()->toISOString(),
                        'no_show_reason' => 'automatic_15min',
                    ]),
                ]);

                $noShowCount++;
            }
        }

        $this->info("Marked {$noShowCount} bookings as no-show");

        return Command::SUCCESS;
    }
}

