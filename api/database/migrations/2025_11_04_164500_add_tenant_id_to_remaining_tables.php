<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add tenant_id to remaining tenant tables
 * 
 * This migration adds tenant_id columns to tables that were created
 * in tenant databases but don't have tenant_id in the central database yet.
 */
return new class extends Migration
{
    /**
     * Tables that need tenant_id added
     */
    private array $tables = [
        'tickets',
        'ticket_comments',
        'incidents',
        'gate_entries',
        'notices',
        'visitors',
        'visitor_logs',
        'visitor_pre_registrations',
        'attachments',
        'gate_devices',
        'facilities',
        'facility_bookings',
        'leaves',
        'sick_leaves',
        'guest_entries',
        'room_changes',
        'attendance_sessions',
        'attendance_logs',
        'laundry_cycles',
        'laundry_requests',
        'sports_events',
        'sports_enrollments',
        'sports_facilities',
        'sports_blockouts',
        'out_passes',
        'out_pass_histories',
        'out_pass_exports',
        'room_beds',
        'room_allocations',
        // Add any other tenant-scoped tables that need tenant_id
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            // Check if table exists
            if (!Schema::hasTable($table)) {
                continue; // Skip if table doesn't exist
            }

            // Check if tenant_id column already exists
            if (Schema::hasColumn($table, 'tenant_id')) {
                continue; // Skip if column already exists
            }

            // Add tenant_id column
            Schema::table($table, function (Blueprint $table): void {
                $table->string('tenant_id')->nullable()->after('id');
                $table->index('tenant_id');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            // Check if table exists
            if (!Schema::hasTable($table)) {
                continue;
            }

            // Check if tenant_id column exists
            if (!Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            // Remove tenant_id column
            Schema::table($table, function (Blueprint $table): void {
                try {
                    $table->dropIndex([$table . '_tenant_id_index']);
                } catch (\Exception $e) {
                    // Index might not exist or have different name
                }
                $table->dropColumn('tenant_id');
            });
        }
    }
};

