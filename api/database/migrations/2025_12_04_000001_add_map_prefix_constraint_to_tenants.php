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

        // Enforce MAP-* prefix with uppercase letters/numbers/hyphen (length 5-24)
        // Use NOT VALID so existing rows are not blocked, but new inserts/updates must comply.
        DB::statement("
            ALTER TABLE tenants
            ADD CONSTRAINT tenants_code_map_prefix_chk
            CHECK (code ~ '^MAP-[A-Z0-9-]{2,20}$')
            NOT VALID;
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE tenants DROP CONSTRAINT IF EXISTS tenants_code_map_prefix_chk;');
    }
};

