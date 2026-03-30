#!/bin/sh
# Create migration file in container using sh (Alpine compatible)

docker exec map-hms-app sh -c 'cat > /var/www/html/database/migrations/2025_01_15_000001_backfill_tenant_id_for_requests.php' << 'ENDOFFILE'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('leaves') || !Schema::hasTable('students')) {
            Log::warning('Backfill migration: Required tables not found, skipping');
            return;
        }

        try {
            if (Schema::hasColumn('leaves', 'tenant_id') && Schema::hasColumn('leaves', 'student_id')) {
                DB::statement("
                    UPDATE leaves l
                    SET tenant_id = s.tenant_id
                    FROM students s
                    WHERE l.student_id = s.id
                    AND l.tenant_id IS NULL
                    AND s.tenant_id IS NOT NULL
                ");
                Log::info('Backfill: Updated leaves with tenant_id');
            }

            if (Schema::hasColumn('out_passes', 'tenant_id') && Schema::hasColumn('out_passes', 'student_id')) {
                DB::statement("
                    UPDATE out_passes op
                    SET tenant_id = s.tenant_id
                    FROM students s
                    WHERE op.student_id = s.id
                    AND op.tenant_id IS NULL
                    AND s.tenant_id IS NOT NULL
                ");
                Log::info('Backfill: Updated out_passes with tenant_id');
            }

            if (Schema::hasColumn('guest_entries', 'tenant_id') && Schema::hasColumn('guest_entries', 'student_id')) {
                DB::statement("
                    UPDATE guest_entries ge
                    SET tenant_id = s.tenant_id
                    FROM students s
                    WHERE ge.student_id = s.id
                    AND ge.tenant_id IS NULL
                    AND s.tenant_id IS NOT NULL
                ");
                Log::info('Backfill: Updated guest_entries with tenant_id');
            }

            if (Schema::hasColumn('sick_leaves', 'tenant_id') && Schema::hasColumn('sick_leaves', 'student_id')) {
                DB::statement("
                    UPDATE sick_leaves sl
                    SET tenant_id = s.tenant_id
                    FROM students s
                    WHERE sl.student_id = s.id
                    AND sl.tenant_id IS NULL
                    AND s.tenant_id IS NOT NULL
                ");
                Log::info('Backfill: Updated sick_leaves with tenant_id');
            }

        } catch (\Exception $e) {
            Log::error('Backfill migration failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function down(): void
    {
        // No-op
    }
};
ENDOFFILE
