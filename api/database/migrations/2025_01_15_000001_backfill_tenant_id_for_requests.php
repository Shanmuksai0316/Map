<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Backfills tenant_id for existing leave, outpass, and guest entry records
     * based on their associated student's tenant_id.
     */
    public function up(): void
    {
        // Only run if we're in a context where we can access the central database
        if (!Schema::hasTable('leaves') || !Schema::hasTable('students')) {
            Log::warning('Backfill migration: Required tables not found, skipping');
            return;
        }

        try {
            // Backfill tenant_id for leaves
            if (Schema::hasColumn('leaves', 'tenant_id') && Schema::hasColumn('leaves', 'student_id')) {
                DB::statement("
                    UPDATE leaves l
                    SET tenant_id = s.tenant_id
                    FROM students s
                    WHERE l.student_id = s.id
                    AND l.tenant_id IS NULL
                    AND s.tenant_id IS NOT NULL
                ");
                
                $leavesUpdated = DB::selectOne("
                    SELECT COUNT(*) as count 
                    FROM leaves 
                    WHERE tenant_id IS NOT NULL
                ");
                
                Log::info("Backfill: Updated leaves with tenant_id", ['count' => $leavesUpdated->count ?? 0]);
            }

            // Backfill tenant_id for out_passes
            if (Schema::hasColumn('out_passes', 'tenant_id') && Schema::hasColumn('out_passes', 'student_id')) {
                DB::statement("
                    UPDATE out_passes op
                    SET tenant_id = s.tenant_id
                    FROM students s
                    WHERE op.student_id = s.id
                    AND op.tenant_id IS NULL
                    AND s.tenant_id IS NOT NULL
                ");
                
                $outpassesUpdated = DB::selectOne("
                    SELECT COUNT(*) as count 
                    FROM out_passes 
                    WHERE tenant_id IS NOT NULL
                ");
                
                Log::info("Backfill: Updated out_passes with tenant_id", ['count' => $outpassesUpdated->count ?? 0]);
            }

            // Backfill tenant_id for guest_entries
            if (Schema::hasColumn('guest_entries', 'tenant_id') && Schema::hasColumn('guest_entries', 'student_id')) {
                DB::statement("
                    UPDATE guest_entries ge
                    SET tenant_id = s.tenant_id
                    FROM students s
                    WHERE ge.student_id = s.id
                    AND ge.tenant_id IS NULL
                    AND s.tenant_id IS NOT NULL
                ");
                
                $guestEntriesUpdated = DB::selectOne("
                    SELECT COUNT(*) as count 
                    FROM guest_entries 
                    WHERE tenant_id IS NOT NULL
                ");
                
                Log::info("Backfill: Updated guest_entries with tenant_id", ['count' => $guestEntriesUpdated->count ?? 0]);
            }

            // Backfill tenant_id for sick_leaves
            if (Schema::hasColumn('sick_leaves', 'tenant_id') && Schema::hasColumn('sick_leaves', 'student_id')) {
                DB::statement("
                    UPDATE sick_leaves sl
                    SET tenant_id = s.tenant_id
                    FROM students s
                    WHERE sl.student_id = s.id
                    AND sl.tenant_id IS NULL
                    AND s.tenant_id IS NOT NULL
                ");
                
                $sickLeavesUpdated = DB::selectOne("
                    SELECT COUNT(*) as count 
                    FROM sick_leaves 
                    WHERE tenant_id IS NOT NULL
                ");
                
                Log::info("Backfill: Updated sick_leaves with tenant_id", ['count' => $sickLeavesUpdated->count ?? 0]);
            }

        } catch (\Exception $e) {
            Log::error('Backfill migration failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Don't throw - allow migration to continue
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // This migration only backfills data, so down() doesn't need to do anything
        // We don't want to clear tenant_id values as that would break functionality
    }
};
