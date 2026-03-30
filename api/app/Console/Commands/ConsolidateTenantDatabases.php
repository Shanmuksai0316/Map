<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Consolidate Tenant Databases into Single Shared Database
 * 
 * This command migrates data from existing tenant databases (database-per-tenant)
 * to the central shared database (single database with tenant_id).
 * 
 * Usage:
 *   php artisan tenants:consolidate [--tenant=uuid] [--dry-run] [--force]
 * 
 * Options:
 *   --tenant=uuid    Consolidate specific tenant only (default: all tenants)
 *   --dry-run        Show what would be migrated without actually migrating
 *   --force          Skip confirmation prompts
 */
class ConsolidateTenantDatabases extends Command
{
    protected $signature = 'tenants:consolidate 
                            {--tenant= : Specific tenant ID to consolidate}
                            {--dry-run : Show what would be migrated without migrating}
                            {--force : Skip confirmation}';

    protected $description = 'Consolidate tenant databases into single shared database';

    /**
     * Tables to migrate from tenant databases to central database
     */
    private array $tenantTables = [
        'users',
        'campuses',
        'hostels',
        'rooms',
        'room_beds',
        'room_allocations',
        'students',
        'staff_assignments',
        'hostel_amenities',
        'hostel_modules',
        'tickets',
        'ticket_comments',
        'incidents',
        'gate_entries',
        'out_passes',
        'out_pass_histories',
        'out_pass_exports',
        'attendance_sessions',
        'attendance_logs',
        'laundry_cycles',
        'laundry_requests',
        'sports_events',
        'sports_enrollments',
        'sports_facilities',
        'sports_blockouts',
        'notices',
        'visitors',
        'visitor_logs',
        'visitor_pre_registrations',
        'attachments',
        'gate_devices',
        'facilities',
        'facility_bookings',
        'leaves',
        'sick_leaves',
        'guest_entries',
        'room_changes',
    ];

    public function handle(): int
    {
        $this->info('🔄 Starting Tenant Database Consolidation...');
        $this->newLine();

        // Check if we're on PostgreSQL
        if (DB::getDriverName() !== 'pgsql') {
            $this->error('❌ This command only works with PostgreSQL!');
            return Command::FAILURE;
        }

        // Get tenants to process
        $tenantId = $this->option('tenant');
        $tenants = $tenantId 
            ? Tenant::where('id', $tenantId)->get()
            : Tenant::all();

        if ($tenants->isEmpty()) {
            $this->error('❌ No tenants found!');
            return Command::FAILURE;
        }

        $this->info("📊 Found {$tenants->count()} tenant(s) to consolidate");
        $this->newLine();

        // Confirm before proceeding
        if (!$this->option('force') && !$this->option('dry-run')) {
            if (!$this->confirm('⚠️  This will migrate data from tenant databases to central database. Continue?')) {
                $this->info('Cancelled.');
                return Command::SUCCESS;
            }
        }

        $isDryRun = $this->option('dry-run');
        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No data will be migrated');
            $this->newLine();
        }

        $totalMigrated = 0;
        $errors = [];

        foreach ($tenants as $tenant) {
            $this->info("Processing tenant: {$tenant->name} ({$tenant->id})");
            
            try {
                $migrated = $this->consolidateTenant($tenant, $isDryRun);
                $totalMigrated += $migrated;
                $this->info("  ✅ Migrated {$migrated} records");
            } catch (\Exception $e) {
                $error = "  ❌ Error: {$e->getMessage()}";
                $this->error($error);
                $errors[] = [$tenant->id => $error];
            }
            
            $this->newLine();
        }

        // Summary
        $this->info('📋 Consolidation Summary:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Tenants Processed', $tenants->count()],
                ['Total Records Migrated', $totalMigrated],
                ['Errors', count($errors)],
            ]
        );

        if (!empty($errors)) {
            $this->error('❌ Errors occurred during consolidation:');
            foreach ($errors as $error) {
                $this->error(json_encode($error));
            }
            return Command::FAILURE;
        }

        if ($isDryRun) {
            $this->warn('⚠️  This was a dry run. Run without --dry-run to actually migrate data.');
        } else {
            $this->info('✅ Consolidation complete!');
        }

        return Command::SUCCESS;
    }

    /**
     * Consolidate a single tenant's database
     */
    private function consolidateTenant(Tenant $tenant, bool $dryRun = false): int
    {
        $tenantDbName = 'tenant_' . $tenant->id;
        $totalMigrated = 0;

        // Check if tenant database exists
        $dbExists = DB::selectOne("
            SELECT EXISTS (
                SELECT FROM pg_database WHERE datname = ?
            ) as exists
        ", [$tenantDbName]);

        if (!$dbExists->exists) {
            $this->warn("  ⚠️  Tenant database '{$tenantDbName}' does not exist. Skipping.");
            return 0;
        }

        // Connect to tenant database
        $tenantConnection = $this->createTenantConnection($tenantDbName);

        foreach ($this->tenantTables as $table) {
            // Check if table exists in tenant database
            $tableExists = DB::connection($tenantConnection)->selectOne("
                SELECT EXISTS (
                    SELECT FROM information_schema.tables 
                    WHERE table_schema = 'public' 
                    AND table_name = ?
                ) as exists
            ", [$table]);

            if (!$tableExists->exists) {
                continue; // Skip if table doesn't exist
            }

            // Get count of records
            $count = DB::connection($tenantConnection)->table($table)->count();
            
            if ($count === 0) {
                continue; // Skip empty tables
            }

            $this->line("  📦 Table: {$table} ({$count} records)");

            if ($dryRun) {
                // Show what would be migrated
                $sample = DB::connection($tenantConnection)
                    ->table($table)
                    ->limit(3)
                    ->get();
                
                $this->line("     Sample records:");
                foreach ($sample as $record) {
                    $this->line("       " . json_encode((array)$record));
                }
                $totalMigrated += $count;
                continue;
            }

            // Check if tenant_id column exists in tenant database
            $hasTenantId = DB::connection($tenantConnection)->selectOne("
                SELECT EXISTS (
                    SELECT FROM information_schema.columns 
                    WHERE table_schema = 'public' 
                    AND table_name = ? 
                    AND column_name = 'tenant_id'
                ) as exists
            ", [$table]);

            if ($hasTenantId->exists) {
                // Tenant database already has tenant_id - just copy data
                $migrated = $this->migrateTableWithTenantId(
                    $tenantConnection,
                    $table,
                    $tenant->id
                );
            } else {
                // Tenant database doesn't have tenant_id - add it during migration
                $migrated = $this->migrateTableWithoutTenantId(
                    $tenantConnection,
                    $table,
                    $tenant->id
                );
            }

            $totalMigrated += $migrated;
            $this->line("     ✅ Migrated {$migrated} records");
        }

        // Clean up connection
        config(["database.connections.{$tenantConnection}" => null]);

        return $totalMigrated;
    }

    /**
     * Create a connection to tenant database
     */
    private function createTenantConnection(string $dbName): string
    {
        $connectionName = "tenant_consolidation_{$dbName}";
        
        $config = config('database.connections.pgsql');
        $config['database'] = $dbName;
        
        config(["database.connections.{$connectionName}" => $config]);
        
        return $connectionName;
    }

    /**
     * Migrate table that already has tenant_id
     */
    private function migrateTableWithTenantId(string $tenantConnection, string $table, string $tenantId): int
    {
        // Get all records from tenant database
        $records = DB::connection($tenantConnection)->table($table)->get();
        
        if ($records->isEmpty()) {
            return 0;
        }

        // Check if tenant_id column exists in central database
        if (!Schema::hasColumn($table, 'tenant_id')) {
            $this->warn("     ⚠️  Central table '{$table}' doesn't have tenant_id column. Skipping.");
            return 0;
        }

        // Insert records into central database
        // Use chunking for large tables
        $chunks = $records->chunk(100);
        $migrated = 0;

        foreach ($chunks as $chunk) {
            // Convert to array and ensure tenant_id is set
            $data = $chunk->map(function ($record) use ($tenantId) {
                $array = (array) $record;
                $array['tenant_id'] = $tenantId; // Ensure tenant_id is set
                return $array;
            })->toArray();

            // Use insertOrIgnore to avoid duplicates
            DB::table($table)->insertOrIgnore($data);
            $migrated += count($data);
        }

        return $migrated;
    }

    /**
     * Migrate table that doesn't have tenant_id
     */
    private function migrateTableWithoutTenantId(string $tenantConnection, string $table, string $tenantId): int
    {
        // Get all records from tenant database
        $records = DB::connection($tenantConnection)->table($table)->get();
        
        if ($records->isEmpty()) {
            return 0;
        }

        // Check if tenant_id column exists in central database
        if (!Schema::hasColumn($table, 'tenant_id')) {
            $this->warn("     ⚠️  Central table '{$table}' doesn't have tenant_id column. Skipping.");
            return 0;
        }

        // Insert records into central database with tenant_id
        $chunks = $records->chunk(100);
        $migrated = 0;

        foreach ($chunks as $chunk) {
            $data = $chunk->map(function ($record) use ($tenantId) {
                $array = (array) $record;
                $array['tenant_id'] = $tenantId; // Add tenant_id
                return $array;
            })->toArray();

            // Use insertOrIgnore to avoid duplicates
            DB::table($table)->insertOrIgnore($data);
            $migrated += count($data);
        }

        return $migrated;
    }
}

