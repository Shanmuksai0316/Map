<?php

namespace App\Console\Commands;

use App\Models\ExportJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PurgeExpiredExportsCommand extends Command
{
    protected $signature = 'app:purge-expired-exports {--dry-run : Show what would be purged without purging}';

    protected $description = 'Purges expired export files (7 days after creation)';

    public function handle(): int
    {
        $retentionDays = 7;
        $cutoffDate = now()->subDays($retentionDays);
        
        $this->info("Purging export jobs older than {$cutoffDate->toDateString()}...");

        $query = ExportJob::where('created_at', '<', $cutoffDate)
            ->where('status', 'ready');

        if ($this->option('dry-run')) {
            $count = $query->count();
            $this->warn("[DRY RUN] Would purge {$count} export jobs and their S3 files.");
            return Command::SUCCESS;
        }

        $purgedCount = 0;
        
        $query->chunkById(100, function ($exports) use (&$purgedCount) {
            foreach ($exports as $export) {
                // Delete from S3
                if ($export->file_url) {
                    $path = parse_url($export->file_url, PHP_URL_PATH);
                    if ($path && Storage::disk('s3')->exists($path)) {
                        Storage::disk('s3')->delete($path);
                        $this->info("Deleted S3 file: {$path}");
                    }
                }
                
                // Delete record
                $export->delete();
                $purgedCount++;
            }
        });

        Log::info("Purged {$purgedCount} expired export jobs.");
        $this->info("✓ Purged {$purgedCount} expired export jobs.");

        return Command::SUCCESS;
    }
}

