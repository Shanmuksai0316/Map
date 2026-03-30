<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PurgeOldAuditLogsCommand extends Command
{
    protected $signature = 'app:purge-old-audit-logs {--dry-run : Show what would be deleted without deleting}';

    protected $description = 'Purges audit logs older than 7 years (retention policy)';

    public function handle(): int
    {
        $retentionYears = 7;
        $cutoffDate = now()->subYears($retentionYears);
        
        $this->info("Purging audit logs older than {$cutoffDate->toDateString()}...");

        if ($this->option('dry-run')) {
            $count = AuditLog::where('created_at', '<', $cutoffDate)->count();
            $this->warn("[DRY RUN] Would delete {$count} audit log entries.");
            return Command::SUCCESS;
        }

        $deletedCount = 0;
        
        // Delete in chunks to avoid memory issues
        AuditLog::where('created_at', '<', $cutoffDate)
            ->chunkById(1000, function ($logs) use (&$deletedCount) {
                $count = $logs->count();
                AuditLog::whereIn('id', $logs->pluck('id'))->delete();
                $deletedCount += $count;
                
                $this->info("Deleted {$count} audit logs...");
            });

        Log::info("Purged {$deletedCount} audit logs older than {$retentionYears} years.");
        $this->info("✓ Purged {$deletedCount} audit log entries.");

        return Command::SUCCESS;
    }
}

