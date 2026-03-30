<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Enable PostgreSQL Row Level Security (RLS) Policies
 * 
 * This migration enables RLS on all tenant-scoped tables to enforce
 * tenant isolation at the database level. This provides an additional
 * layer of security on top of application-level TenantScope.
 * 
 * RLS policies automatically filter all queries by tenant_id, ensuring
 * that even if application code accidentally omits tenant_id filtering,
 * the database will still enforce isolation.
 */
return new class extends Migration
{
    /**
     * Tables that need RLS policies (all tenant-scoped tables)
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
        // Add any other tenant-scoped tables here
    ];

    public function up(): void
    {
        // Only run on PostgreSQL
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Create function to get current tenant ID from session
        // Use public schema instead of app schema
        DB::statement("
            CREATE OR REPLACE FUNCTION public.current_tenant_id()
            RETURNS TEXT AS \$\$
            BEGIN
                -- Try to get from session variable first
                RETURN current_setting('app.current_tenant_id', true);
            EXCEPTION
                WHEN OTHERS THEN
                    -- Return NULL if not set
                    RETURN NULL;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        // Enable RLS and create policies for each tenant table
        foreach ($this->tenantTables as $table) {
            // Check if table exists
            $exists = DB::selectOne("
                SELECT EXISTS (
                    SELECT FROM information_schema.tables 
                    WHERE table_schema = 'public' 
                    AND table_name = ?
                ) as exists
            ", [$table]);

            if (!$exists->exists) {
                continue; // Skip if table doesn't exist yet
            }

            // Check if tenant_id column exists
            $hasTenantId = DB::selectOne("
                SELECT EXISTS (
                    SELECT FROM information_schema.columns 
                    WHERE table_schema = 'public' 
                    AND table_name = ? 
                    AND column_name = 'tenant_id'
                ) as exists
            ", [$table]);

            if (!$hasTenantId->exists) {
                continue; // Skip if tenant_id column doesn't exist
            }

            // Enable RLS on table
            DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY;");

            // Drop existing policy if it exists
            DB::statement("DROP POLICY IF EXISTS tenant_isolation_policy ON {$table};");

            // Create policy that filters by tenant_id
            // The policy allows access if:
            // 1. tenant_id matches current_tenant_id() (from session), OR
            // 2. current_tenant_id() is NULL (Super Admin bypass)
            DB::statement("
                CREATE POLICY tenant_isolation_policy ON {$table}
                FOR ALL
                USING (
                    tenant_id = public.current_tenant_id()::text 
                    OR public.current_tenant_id() IS NULL
                )
                WITH CHECK (
                    tenant_id = public.current_tenant_id()::text 
                    OR public.current_tenant_id() IS NULL
                );
            ");
        }
    }

    public function down(): void
    {
        // Only run on PostgreSQL
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Drop policies and disable RLS
        foreach ($this->tenantTables as $table) {
            // Check if table exists
            $exists = DB::selectOne("
                SELECT EXISTS (
                    SELECT FROM information_schema.tables 
                    WHERE table_schema = 'public' 
                    AND table_name = ?
                ) as exists
            ", [$table]);

            if (!$exists->exists) {
                continue;
            }

            // Drop policy
            DB::statement("DROP POLICY IF EXISTS tenant_isolation_policy ON {$table};");

            // Disable RLS
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY;");
        }

        // Drop function
        DB::statement("DROP FUNCTION IF EXISTS public.current_tenant_id();");
    }
};

