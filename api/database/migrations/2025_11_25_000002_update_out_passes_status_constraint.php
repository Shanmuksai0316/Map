<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // PostgreSQL uses CHECK constraints for enums - we need to drop and recreate
        // First, alter the column type to varchar to remove the enum constraint
        DB::statement("ALTER TABLE out_passes ALTER COLUMN status TYPE varchar(50)");
        
        // Drop old constraint if it exists
        DB::statement("ALTER TABLE out_passes DROP CONSTRAINT IF EXISTS out_passes_status_check");
        
        // Add new constraint with all valid statuses
        DB::statement("ALTER TABLE out_passes ADD CONSTRAINT out_passes_status_check CHECK (status IN ('pending', 'approved', 'declined', 'rejected', 'cancelled', 'expired'))");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE out_passes DROP CONSTRAINT IF EXISTS out_passes_status_check");
        DB::statement("ALTER TABLE out_passes ADD CONSTRAINT out_passes_status_check CHECK (status IN ('pending', 'approved', 'rejected', 'cancelled'))");
    }
};

