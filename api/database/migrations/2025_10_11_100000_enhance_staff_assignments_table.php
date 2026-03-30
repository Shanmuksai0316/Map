<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Enhances staff_assignments table to support:
     * - Cross-tenant staff reassignment
     * - Assignment metadata (who assigned, why)
     * - One active assignment per staff constraint
     * - Complete audit trail
     */
    public function up(): void
    {
        // Add assignment and revocation metadata columns
        Schema::table('staff_assignments', function (Blueprint $table) {
            $table->unsignedBigInteger('assigned_by')->nullable()->after('hostel_id');
            $table->text('assignment_notes')->nullable()->after('assigned_by');
            $table->text('revocation_reason')->nullable()->after('revoked_at');
            $table->unsignedBigInteger('revoked_by')->nullable()->after('revocation_reason');
            
            // Add indexes for performance
            $table->index(['tenant_id', 'hostel_id'], 'idx_staff_tenant_hostel');
            $table->index(['user_id', 'revoked_at'], 'idx_staff_user_revoked');
        });

        // Drop old unique constraint (tenant_id, user_id, hostel_id)
        Schema::table('staff_assignments', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'user_id', 'hostel_id']);
        });

        // Add partial unique index: ONE active assignment per staff
        // This ensures a staff can only be assigned to one hostel at a time
        DB::statement('
            CREATE UNIQUE INDEX idx_staff_one_active_assignment 
            ON staff_assignments(user_id) 
            WHERE revoked_at IS NULL
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop partial unique index
        DB::statement('DROP INDEX IF EXISTS idx_staff_one_active_assignment');

        // Restore old unique constraint
        Schema::table('staff_assignments', function (Blueprint $table) {
            $table->unique(['tenant_id', 'user_id', 'hostel_id']);
        });

        // Drop new columns and indexes
        Schema::table('staff_assignments', function (Blueprint $table) {
            $table->dropIndex('idx_staff_tenant_hostel');
            $table->dropIndex('idx_staff_user_revoked');
            $table->dropColumn([
                'assigned_by',
                'assignment_notes',
                'revocation_reason',
                'revoked_by'
            ]);
        });
    }
};


