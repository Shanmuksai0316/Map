<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // No-op migration; KPI seed handled in TestingBaselineSeeder.
        // Kept to ensure migration ordering doesn’t break test seeds.
    }

    public function down(): void
    {
        // no-op
    }
};

