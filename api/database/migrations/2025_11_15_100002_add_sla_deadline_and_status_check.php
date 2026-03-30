<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tickets')) {
            return;
        }

        Schema::table('tickets', function (Blueprint $table): void {
            if (! Schema::hasColumn('tickets', 'sla_deadline')) {
                $table->timestamp('sla_deadline')->nullable()->after('sla_due_at');
            }
        });

        // Constraint DDL below is PostgreSQL-specific.
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE tickets DROP CONSTRAINT IF EXISTS tickets_status_check');
            DB::statement("ALTER TABLE tickets ADD CONSTRAINT tickets_status_check CHECK (status IN ('open','in_progress','resolved','closed','on_hold'))");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('tickets')) {
            return;
        }

        Schema::table('tickets', function (Blueprint $table): void {
            if (Schema::hasColumn('tickets', 'sla_deadline')) {
                $table->dropColumn('sla_deadline');
            }
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE tickets DROP CONSTRAINT IF EXISTS tickets_status_check');
            DB::statement("ALTER TABLE tickets ADD CONSTRAINT tickets_status_check CHECK (status IN ('open','in_progress','resolved','closed'))");
        }
    }
};
