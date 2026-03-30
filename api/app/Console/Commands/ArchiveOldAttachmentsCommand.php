<?php

namespace App\Console\Commands;

use App\Models\Attachment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ArchiveOldAttachmentsCommand extends Command
{
    protected $signature = 'app:archive-old-attachments {--dry-run : Show what would be archived without archiving}';

    protected $description = 'Moves attachments older than 1 year from hot to cold S3 storage (lifecycle)';

    public function handle(): int
    {
        $retentionYears = 1;
        $cutoffDate = now()->subYear();
        
        $this->info("Moving attachments older than {$cutoffDate->toDateString()} to cold storage...");

        $query = Attachment::where('created_at', '<', $cutoffDate)
            ->whereNull('archived_at');

        if ($this->option('dry-run')) {
            $count = $query->count();
            $this->warn("[DRY RUN] Would move {$count} attachments to cold storage.");
            return Command::SUCCESS;
        }

        $archivedCount = 0;
        
        // NOTE: Actual S3 lifecycle transition should be configured via S3 bucket policies
        // This command just marks records as archived for tracking
        $query->chunkById(500, function ($attachments) use (&$archivedCount) {
            foreach ($attachments as $attachment) {
                // In production, S3 lifecycle policies handle the actual transition
                // We just mark the record
                $attachment->update(['archived_at' => now()]);
                $archivedCount++;
            }
            
            $this->info("Marked {$attachments->count()} attachments as archived...");
        });

        Log::info("Archived {$archivedCount} attachments older than {$retentionYears} year.");
        $this->info("✓ Marked {$archivedCount} attachments for cold storage.");
        $this->warn("⚠️  Configure S3 lifecycle policies to transition to Glacier after 1 year.");

        return Command::SUCCESS;
    }
}

