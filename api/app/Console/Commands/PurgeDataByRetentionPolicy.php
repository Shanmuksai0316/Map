<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class PurgeDataByRetentionPolicy extends Command
{
    protected $signature = 'retention:purge';

    protected $description = 'Purge data according to data_retention_policies';

    /**
    * Supported entity => table mapping.
    */
    private array $entityTables = [
        'audit_logs' => 'audit_logs',
        'product_events' => 'product_events',
        'export_jobs' => 'export_jobs',
        'import_jobs' => 'import_jobs',
    ];

    public function handle(): int
    {
        $policies = DB::table('data_retention_policies')
            ->where('enabled', true)
            ->get();

        $purgedTotal = 0;

        foreach ($policies as $policy) {
            $table = $this->entityTables[$policy->entity] ?? null;
            if (!$table || !Schema::hasTable($table)) {
                Log::warning('Retention: unsupported entity', ['entity' => $policy->entity]);
                continue;
            }

            $cutoff = Carbon::now()->subDays((int) $policy->retention_days);

            $query = DB::table($table)->where('created_at', '<', $cutoff);
            if ($policy->tenant_id) {
                $query->where('tenant_id', $policy->tenant_id);
            }

            $count = $query->delete();
            $purgedTotal += $count;

            $this->info("Purged {$count} rows from {$table} (entity: {$policy->entity})");
        }

        $this->info("Total purged: {$purgedTotal}");
        return self::SUCCESS;
    }
}
