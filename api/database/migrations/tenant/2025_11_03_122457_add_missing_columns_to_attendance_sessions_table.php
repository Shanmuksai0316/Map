<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds missing columns to attendance_sessions table to match Domain model expectations:
     * - metadata (JSONB) - for storing window times, counts, etc.
     * - campus_id (foreign key, nullable) - optional campus reference
     * - name (string) - session name/description
     * - kind (string) - session type (roll_call, event, night_check)
     * - scheduled_at (timestamp, nullable) - scheduled datetime (computed from session_date + session_time)
     */
    public function up(): void
    {
        if (! Schema::hasTable('attendance_sessions')) {
            return;
        }

        Schema::table('attendance_sessions', function (Blueprint $table) {
            // Add missing columns
            $table->foreignId('campus_id')->nullable()->after('hostel_id')->constrained()->nullOnDelete();
            $table->string('name')->nullable()->after('campus_id');
            $table->string('kind')->nullable()->after('name');
            $table->jsonb('metadata')->nullable()->after('status');
            $table->timestamp('scheduled_at')->nullable()->after('session_time');
            
            // Add indexes
            $table->index('campus_id');
            $table->index(['kind', 'status']);
        });
        
        // Backfill scheduled_at from session_date + session_time for existing records
        DB::statement("
            UPDATE attendance_sessions 
            SET scheduled_at = (session_date + session_time)::timestamp 
            WHERE scheduled_at IS NULL 
            AND session_date IS NOT NULL 
            AND session_time IS NOT NULL
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('attendance_sessions')) {
            return;
        }

        Schema::table('attendance_sessions', function (Blueprint $table) {
            $table->dropForeign(['campus_id']);
            $table->dropColumn(['campus_id', 'name', 'kind', 'metadata', 'scheduled_at']);
        });
    }
};
