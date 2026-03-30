<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Create PostgreSQL Row-Level Security (RLS) policies for tenant isolation
 * 
 * This migration enables RLS and creates policies to enforce tenant isolation
 * at the database level. This provides a second layer of security beyond
 * application-level TenantScope.
 * 
 * RLS policies ensure that:
 * - Users can only SELECT/INSERT/UPDATE/DELETE rows where tenant_id matches their tenant_id
 * - Super Admins can access all tenants (bypass via application layer)
 * - Policies are automatically applied to all queries
 */
return new class extends Migration
{
    /**
     * Tables that need RLS policies
     */
    private array $tables = [
        'users',
        'students',
        'campuses',
        'hostels',
        'rooms',
        'room_beds',
        'room_allocations',
        'tickets',
        'ticket_comments',
        'incidents',
        'gate_entries',
        'notices',
        'visitors',
        'visitor_logs',
        'attendance_sessions',
        'attendance_logs',
        'out_passes',
        'laundry_cycles',
        'laundry_requests',
        'sports_events',
        'sports_enrollments',
        'sports_facilities',
        'facilities',
        'facility_bookings',
        'leaves',
        'sick_leaves',
        'guest_entries',
        'room_changes',
    ];

    public function up(): void
    {
        // RLS policies are PostgreSQL-specific.
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Enable RLS on all tenant-scoped tables
        foreach ($this->tables as $table) {
            // Check if table exists
            $tableExists = DB::selectOne("
                SELECT 1 FROM information_schema.tables 
                WHERE table_schema = 'public' AND table_name = ?
            ", [$table]);

            if (!$tableExists) {
                continue;
            }

            // Enable RLS on table
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");

            // Create policy for SELECT (read)
            $this->createPolicy($table, 'SELECT', "
                EXISTS (
                    SELECT 1 FROM users 
                    WHERE users.id = current_setting('app.current_user_id', true)::bigint
                    AND users.tenant_id = {$table}.tenant_id
                )
                OR current_setting('app.is_super_admin', true)::boolean = true
            ");

            // Create policy for INSERT (create)
            $this->createPolicy($table, 'INSERT', "
                EXISTS (
                    SELECT 1 FROM users 
                    WHERE users.id = current_setting('app.current_user_id', true)::bigint
                    AND users.tenant_id = {$table}.tenant_id
                )
                OR current_setting('app.is_super_admin', true)::boolean = true
            ");

            // Create policy for UPDATE (modify)
            $this->createPolicy($table, 'UPDATE', "
                EXISTS (
                    SELECT 1 FROM users 
                    WHERE users.id = current_setting('app.current_user_id', true)::bigint
                    AND users.tenant_id = {$table}.tenant_id
                )
                OR current_setting('app.is_super_admin', true)::boolean = true
            ");

            // Create policy for DELETE (remove)
            $this->createPolicy($table, 'DELETE', "
                EXISTS (
                    SELECT 1 FROM users 
                    WHERE users.id = current_setting('app.current_user_id', true)::bigint
                    AND users.tenant_id = {$table}.tenant_id
                )
                OR current_setting('app.is_super_admin', true)::boolean = true
            ");
        }
    }

    /**
     * Create a RLS policy for a table
     */
    private function createPolicy(string $table, string $operation, string $expression): void
    {
        $policyName = "{$table}_{$operation}_tenant_policy";

        // Drop policy if exists
        DB::statement("DROP POLICY IF EXISTS {$policyName} ON {$table}");

        // Build appropriate clause based on operation type
        $clause = match ($operation) {
            'SELECT', 'DELETE' => "USING ({$expression})",
            'INSERT' => "WITH CHECK ({$expression})",
            'UPDATE' => "USING ({$expression}) WITH CHECK ({$expression})",
            default => "USING ({$expression})",
        };

        DB::statement("
            CREATE POLICY {$policyName}
            ON {$table}
            FOR {$operation}
            {$clause}
        ");
    }

    public function down(): void
    {
        // RLS policies are PostgreSQL-specific.
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->tables as $table) {
            // Check if table exists
            $tableExists = DB::selectOne("
                SELECT 1 FROM information_schema.tables 
                WHERE table_schema = 'public' AND table_name = ?
            ", [$table]);

            if (!$tableExists) {
                continue;
            }

            // Drop all policies
            $operations = ['SELECT', 'INSERT', 'UPDATE', 'DELETE'];
            foreach ($operations as $operation) {
                $policyName = "{$table}_{$operation}_tenant_policy";
                DB::statement("DROP POLICY IF EXISTS {$policyName} ON {$table}");
            }

            // Disable RLS
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }
    }
};
