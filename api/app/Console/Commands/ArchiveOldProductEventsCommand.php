<?php

namespace App\Console\Commands;

use App\Models\ProductEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ArchiveOldProductEventsCommand extends Command
{
    protected $signature = 'app:archive-old-product-events {--dry-run : Show what would be archived without archiving}';

    protected $description = 'Archives product events older than 3 years to S3 and removes from DB';

    public function handle(): int
    {
        $retentionYears = 3;
        $cutoffDate = now()->subYears($retentionYears);
        
        $this->info("Archiving product events older than {$cutoffDate->toDateString()}...");

        if ($this->option('dry-run')) {
            $count = ProductEvent::where('happened_at', '<', $cutoffDate)->count();
            $this->warn("[DRY RUN] Would archive {$count} product events.");
            return Command::SUCCESS;
        }

        $archivedCount = 0;
        
        // Archive in chunks
        ProductEvent::where('happened_at', '<', $cutoffDate)
            ->orderBy('happened_at')
            ->chunkById(5000, function ($events) use (&$archivedCount, $cutoffDate) {
                // Export to JSON
                $data = $events->toArray();
                $filename = "archives/product_events_" . now()->format('Y-m-d_His') . "_chunk_{$archivedCount}.json";
                
                // Upload to S3
                Storage::disk('s3')->put($filename, json_encode($data, JSON_PRETTY_PRINT));
                
                // Delete from database
                ProductEvent::whereIn('id', $events->pluck('id'))->delete();
                
                $archivedCount += $events->count();
                $this->info("Archived {$events->count()} events to {$filename}...");
            });

        Log::info("Archived {$archivedCount} product events older than {$retentionYears} years.");
        $this->info("✓ Archived {$archivedCount} product events to S3.");

        return Command::SUCCESS;
    }
}

