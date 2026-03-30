#!/bin/sh
# Run SQL directly to backfill tenant_id (simpler than migration)

docker exec map-hms-app php artisan tinker --execute="
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

try {
    // Backfill leaves
    \$updated = DB::statement(\"
        UPDATE leaves l
        SET tenant_id = s.tenant_id
        FROM students s
        WHERE l.student_id = s.id
        AND l.tenant_id IS NULL
        AND s.tenant_id IS NOT NULL
    \");
    Log::info('Backfill: Updated leaves with tenant_id');
    
    // Backfill out_passes
    DB::statement(\"
        UPDATE out_passes op
        SET tenant_id = s.tenant_id
        FROM students s
        WHERE op.student_id = s.id
        AND op.tenant_id IS NULL
        AND s.tenant_id IS NOT NULL
    \");
    Log::info('Backfill: Updated out_passes with tenant_id');
    
    // Backfill guest_entries
    DB::statement(\"
        UPDATE guest_entries ge
        SET tenant_id = s.tenant_id
        FROM students s
        WHERE ge.student_id = s.id
        AND ge.tenant_id IS NULL
        AND s.tenant_id IS NOT NULL
    \");
    Log::info('Backfill: Updated guest_entries with tenant_id');
    
    // Backfill sick_leaves
    DB::statement(\"
        UPDATE sick_leaves sl
        SET tenant_id = s.tenant_id
        FROM students s
        WHERE sl.student_id = s.id
        AND sl.tenant_id IS NULL
        AND s.tenant_id IS NOT NULL
    \");
    Log::info('Backfill: Updated sick_leaves with tenant_id');
    
    echo 'Backfill completed successfully';
} catch (Exception \$e) {
    Log::error('Backfill failed: ' . \$e->getMessage());
    echo 'Backfill failed: ' . \$e->getMessage();
}
"
