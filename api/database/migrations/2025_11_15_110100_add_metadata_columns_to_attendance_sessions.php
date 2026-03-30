<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $isSqlite = DB::getDriverName() === 'sqlite';

        if (! Schema::hasTable('attendance_sessions')) {
            return;
        }

        Schema::table('attendance_sessions', function (Blueprint $table) use ($isSqlite) {
            if (! Schema::hasColumn('attendance_sessions', 'campus_id')) {
                $table->foreignId('campus_id')->nullable()->after('hostel_id')->constrained()->nullOnDelete();
                $table->index('campus_id');
            }

            if (! Schema::hasColumn('attendance_sessions', 'name')) {
                $table->string('name')->nullable()->after('campus_id');
            }

            if (! Schema::hasColumn('attendance_sessions', 'kind')) {
                $table->string('kind')->nullable()->after('name');
                $table->index(['kind', 'status']);
            }

            if (! Schema::hasColumn('attendance_sessions', 'metadata')) {
                if ($isSqlite) {
                    $table->json('metadata')->nullable()->after('status');
                } else {
                    $table->jsonb('metadata')->nullable()->after('status');
                }
            }

            if (! Schema::hasColumn('attendance_sessions', 'scheduled_at')) {
                $table->timestamp('scheduled_at')->nullable()->after('session_time');
            }
        });

        if (! Schema::hasColumn('attendance_sessions', 'scheduled_at')) {
            return;
        }

        if ($isSqlite) {
            DB::statement("
                UPDATE attendance_sessions
                SET scheduled_at = datetime(session_date || ' ' || session_time)
                WHERE scheduled_at IS NULL
                  AND session_date IS NOT NULL
                  AND session_time IS NOT NULL
            ");
        } else {
            DB::statement("
                UPDATE attendance_sessions
                SET scheduled_at = (session_date + session_time)::timestamp
                WHERE scheduled_at IS NULL
                  AND session_date IS NOT NULL
                  AND session_time IS NOT NULL
            ");

            DB::statement("ALTER TABLE attendance_sessions DROP CONSTRAINT IF EXISTS attendance_sessions_status_check");
            DB::statement("
                ALTER TABLE attendance_sessions
                ADD CONSTRAINT attendance_sessions_status_check
                CHECK (status IN ('pending', 'open', 'in_progress', 'completed', 'closed'))
            ");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('attendance_sessions')) {
            return;
        }

        Schema::table('attendance_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('attendance_sessions', 'campus_id')) {
                $table->dropForeign(['campus_id']);
                $table->dropColumn('campus_id');
            }

            if (Schema::hasColumn('attendance_sessions', 'name')) {
                $table->dropColumn('name');
            }

            if (Schema::hasColumn('attendance_sessions', 'kind')) {
                $table->dropIndex(['kind', 'status']);
                $table->dropColumn('kind');
            }

            if (Schema::hasColumn('attendance_sessions', 'metadata')) {
                $table->dropColumn('metadata');
            }

            if (Schema::hasColumn('attendance_sessions', 'scheduled_at')) {
                $table->dropColumn('scheduled_at');
            }
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE attendance_sessions DROP CONSTRAINT IF EXISTS attendance_sessions_status_check");
            DB::statement("
                ALTER TABLE attendance_sessions
                ADD CONSTRAINT attendance_sessions_status_check
                CHECK (status IN ('pending', 'in_progress', 'completed'))
            ");
        }
    }
};
