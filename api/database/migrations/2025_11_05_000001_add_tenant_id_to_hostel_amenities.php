<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Add tenant_id column to hostel_amenities table
 * 
 * This migration adds tenant_id to hostel_amenities to comply with RLS policies.
 * The tenant_id is required for Row Level Security (RLS) WITH CHECK policies.
 * 
 * Backfills existing records by joining with hostels table.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hostel_amenities')) {
            return;
        }

        Schema::table('hostel_amenities', function (Blueprint $table) {
            if (!Schema::hasColumn('hostel_amenities', 'tenant_id')) {
                $table->string('tenant_id')->nullable()->after('id');
                $table->index('tenant_id');
            }
        });

        // Backfill existing records by joining with hostels table
        if (DB::getDriverName() === 'sqlite') {
            $tenantMap = DB::table('hostels')->pluck('tenant_id', 'id');

            DB::table('hostel_amenities')
                ->whereNull('tenant_id')
                ->select(['id', 'hostel_id'])
                ->orderBy('id')
                ->chunk(100, function ($amenities) use ($tenantMap) {
                    foreach ($amenities as $amenity) {
                        $tenantId = $tenantMap[$amenity->hostel_id] ?? null;
                        if ($tenantId) {
                            DB::table('hostel_amenities')
                                ->where('id', $amenity->id)
                                ->update(['tenant_id' => $tenantId]);
                        }
                    }
                });
        } else {
            DB::statement("
                UPDATE hostel_amenities ha
                SET tenant_id = h.tenant_id
                FROM hostels h
                WHERE ha.hostel_id = h.id
                AND ha.tenant_id IS NULL
            ");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('hostel_amenities')) {
            return;
        }

        Schema::table('hostel_amenities', function (Blueprint $table) {
            if (Schema::hasColumn('hostel_amenities', 'tenant_id')) {
                $table->dropIndex(['tenant_id']);
                $table->dropColumn('tenant_id');
            }
        });
    }
};

