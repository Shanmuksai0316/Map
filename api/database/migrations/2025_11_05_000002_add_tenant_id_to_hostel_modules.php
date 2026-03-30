<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Add tenant_id column to hostel_modules table
 * 
 * This migration adds tenant_id to hostel_modules to comply with RLS policies.
 * The tenant_id is required for Row Level Security (RLS) WITH CHECK policies.
 * 
 * Backfills existing records by joining with hostels table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hostel_modules')) {
            return;
        }

        Schema::table('hostel_modules', function (Blueprint $table) {
            if (!Schema::hasColumn('hostel_modules', 'tenant_id')) {
                $table->string('tenant_id')->nullable()->after('id');
                $table->index('tenant_id');
            }
        });

        // Backfill existing records by joining with hostels table.
        // Use driver-specific SQL because SQLite doesn't support UPDATE ... FROM.
        if (DB::getDriverName() === 'sqlite') {
            DB::statement("
                UPDATE hostel_modules
                SET tenant_id = (
                    SELECT hostels.tenant_id
                    FROM hostels
                    WHERE hostels.id = hostel_modules.hostel_id
                )
                WHERE tenant_id IS NULL
            ");
        } else {
            DB::statement("
                UPDATE hostel_modules hm
                SET tenant_id = h.tenant_id
                FROM hostels h
                WHERE hm.hostel_id = h.id
                AND hm.tenant_id IS NULL
            ");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('hostel_modules')) {
            return;
        }

        Schema::table('hostel_modules', function (Blueprint $table) {
            if (Schema::hasColumn('hostel_modules', 'tenant_id')) {
                $table->dropIndex(['tenant_id']);
                $table->dropColumn('tenant_id');
            }
        });
    }
};
