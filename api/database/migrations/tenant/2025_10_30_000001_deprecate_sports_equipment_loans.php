<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // No-op: Step 1 of 2-step removal for sports equipment loans.
        // Deploy this first to ensure application code no longer references these tables.
    }

    public function down(): void
    {
        // No-op
    }
};


