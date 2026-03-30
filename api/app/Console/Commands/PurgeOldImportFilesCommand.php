<?php

namespace App\Console\Commands;

use App\Models\Import;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PurgeOldImportFilesCommand extends Command
{
    protected $signature = 'app:purge-old-import-files {--dry-run : Show what would be purged without purging}';

    protected $description = 'Purges import CSV files older than 7 days';

    public function handle(): int
    {
        $retentionDays = 7;
        $cutoffDate = now()->subDays($retentionDays);
        
        $this->info("Purging import files older than {$cutoffDate->toDateString()}...");

        $query = Import::where('created_at', '<', $cutoffDate);

        if ($this->option('dry-run')) {
            $count = $query->count();
            $this->warn("[DRY RUN] Would purge {$count} import files.");
            return Command::SUCCESS;
        }

        $purgedCount = 0;
        
        $query->chunkById(100, function ($imports) use (&$purgedCount) {
            foreach ($imports as $import) {
                // Delete from S3
                if ($import->file_path) {
                    if (Storage::disk('s3')->exists($import->file_path)) {
                        Storage::disk('s3')->delete($import->file_path);
                        $this->info("Deleted S3 file: {$import->file_path}");
                    }
                }
                
                // Mark as purged (keep record for audit)
                $import->update(['file_path' => null, 'purged_at' => now()]);
                $purgedCount++;
            }
        });

        Log::info("Purged {$purgedCount} import files older than {$retentionDays} days.");
        $this->info("✓ Purged {$purgedCount} import files.");

        return Command::SUCCESS;
    }
}

