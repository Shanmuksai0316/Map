<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Backfill migration to add tenant_id to existing tenant tables
 * 
 * This migration runs on tenant databases and adds tenant_id columns
 * to tables that were created without them. The tenant_id should be
 * set from the tenant context when records are created.
 * 
 * For existing records, tenant_id will be nullable initially and can
 * be backfilled via a separate artisan command or during record updates.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Add tenant_id to users table if it exists and column missing
        if (Schema::hasTable('users') && !Schema::hasColumn('users', 'tenant_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('tenant_id')->nullable()->after('id');
                $table->index('tenant_id');
            });
        }

        // Add tenant_id to campuses table if it doesn't exist
        if (Schema::hasTable('campuses') && !Schema::hasColumn('campuses', 'tenant_id')) {
            Schema::table('campuses', function (Blueprint $table): void {
                $table->string('tenant_id')->nullable()->after('id');
                $table->index('tenant_id');
            });
        }

        // Add tenant_id to hostels table if it doesn't exist
        if (Schema::hasTable('hostels') && !Schema::hasColumn('hostels', 'tenant_id')) {
            Schema::table('hostels', function (Blueprint $table): void {
                $table->string('tenant_id')->nullable()->after('id');
                $table->index('tenant_id');
            });
        }

        // Add tenant_id to rooms table if it doesn't exist
        if (Schema::hasTable('rooms') && !Schema::hasColumn('rooms', 'tenant_id')) {
            Schema::table('rooms', function (Blueprint $table): void {
                $table->string('tenant_id')->nullable()->after('id');
                $table->index('tenant_id');
            });
        }

        // Add tenant_id to students table if it doesn't exist
        if (Schema::hasTable('students') && !Schema::hasColumn('students', 'tenant_id')) {
            Schema::table('students', function (Blueprint $table): void {
                $table->string('tenant_id')->nullable()->after('id');
                $table->index('tenant_id');
            });
        }

        // Add tenant_id to room_beds table if it doesn't exist
        if (Schema::hasTable('room_beds') && !Schema::hasColumn('room_beds', 'tenant_id')) {
            Schema::table('room_beds', function (Blueprint $table): void {
                $table->string('tenant_id')->nullable()->after('id');
                $table->index('tenant_id');
            });
        }

        // Add tenant_id to room_allocations table if it doesn't exist
        if (Schema::hasTable('room_allocations') && !Schema::hasColumn('room_allocations', 'tenant_id')) {
            Schema::table('room_allocations', function (Blueprint $table): void {
                $table->string('tenant_id')->nullable()->after('id');
                $table->index('tenant_id');
            });
        }
    }

    public function down(): void
    {
        // Remove tenant_id columns if they exist
        $tables = ['users', 'campuses', 'hostels', 'rooms', 'students', 'room_beds', 'room_allocations'];
        
        foreach ($tables as $table) {
            if (Schema::hasColumn($table, 'tenant_id')) {
                Schema::table($table, function (Blueprint $table): void {
                    $table->dropIndex([$table . '_tenant_id_index']);
                    $table->dropColumn('tenant_id');
                });
            }
        }
    }
};

