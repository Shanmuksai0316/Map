<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        if (! Schema::hasTable('attendance_logs')) {
            return;
        }

        DB::statement('ALTER TABLE attendance_logs DROP CONSTRAINT IF EXISTS attendance_logs_status_check');
        DB::statement("ALTER TABLE attendance_logs ALTER COLUMN status TYPE VARCHAR(20)");
        DB::statement("ALTER TABLE attendance_logs ADD CONSTRAINT attendance_logs_status_check CHECK (status IN ('present','absent','excused','late','leave','unmarked'))");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        if (! Schema::hasTable('attendance_logs')) {
            return;
        }

        DB::statement('ALTER TABLE attendance_logs DROP CONSTRAINT IF EXISTS attendance_logs_status_check');
        DB::statement("ALTER TABLE attendance_logs ADD CONSTRAINT attendance_logs_status_check CHECK (status IN ('present','absent','excused'))");
    }
};

