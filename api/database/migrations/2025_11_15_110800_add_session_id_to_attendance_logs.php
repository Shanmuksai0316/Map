<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendance_logs')) {
            return;
        }

        Schema::table('attendance_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('attendance_logs', 'attendance_session_id')) {
                $table->foreignId('attendance_session_id')->nullable()->after('hostel_id')->constrained('attendance_sessions')->nullOnDelete();
            }
        });

        if (! Schema::hasColumn('attendance_logs', 'session_id')) {
            DB::statement('ALTER TABLE attendance_logs ADD COLUMN session_id BIGINT GENERATED ALWAYS AS (attendance_session_id) STORED');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('attendance_logs')) {
            return;
        }

        if (Schema::hasColumn('attendance_logs', 'session_id')) {
            Schema::table('attendance_logs', function (Blueprint $table): void {
                $table->dropColumn('session_id');
            });
        }

        Schema::table('attendance_logs', function (Blueprint $table): void {
            if (Schema::hasColumn('attendance_logs', 'attendance_session_id')) {
                $table->dropConstrainedForeignId('attendance_session_id');
            }
        });
    }
};

