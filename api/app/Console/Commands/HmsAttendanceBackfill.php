<?php

namespace App\Console\Commands;

use App\Jobs\Attendance\OpenSessionsJob;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class HmsAttendanceBackfill extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'hms:attendance:backfill {--days=14 : Number of days to backfill}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill attendance sessions for the specified number of days';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = (int) $this->option('days');
        
        if ($days < 1 || $days > 90) {
            $this->error('Days must be between 1 and 90');
            return 1;
        }
        
        $this->info("Backfilling attendance sessions for {$days} days...");
        
        $today = now('Asia/Kolkata');
        
        for ($i = 0; $i < $days; $i++) {
            $date = $today->copy()->subDays($i);
            $this->info("Creating sessions for {$date->toDateString()}...");
            
            OpenSessionsJob::dispatch($date->toDateString());
        }
        
        $this->info("Backfill completed. Sessions created for {$days} days.");
        return 0;
    }
}
