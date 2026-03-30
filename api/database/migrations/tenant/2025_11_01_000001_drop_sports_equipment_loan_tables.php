<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        // Drop dependent table first
        if (Schema::hasTable('sports_equipment_loans')) {
            Schema::drop('sports_equipment_loans');
        }

        // Then drop equipment master table (not used elsewhere after loans removal)
        if (Schema::hasTable('sports_equipment')) {
            Schema::drop('sports_equipment');
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // Irreversible in this migration; tables intentionally removed.
        // Restore requires re-running original create_sports_tables migration or a dedicated recreate.
    }
};


