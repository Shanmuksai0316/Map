<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE parcels ALTER COLUMN tenant_id TYPE varchar(255) USING tenant_id::varchar");

        // Backfill tenant_id from hostels for existing rows (safe to re-run).
        DB::statement(
            "UPDATE parcels p SET tenant_id = h.tenant_id FROM hostels h WHERE p.hostel_id = h.id"
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Best-effort rollback: cast back to bigint (may fail if tenant_id is non-numeric)
        DB::statement("ALTER TABLE parcels ALTER COLUMN tenant_id TYPE bigint USING tenant_id::bigint");
    }
};
