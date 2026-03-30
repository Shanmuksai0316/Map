<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('out_passes', function (Blueprint $table) {
            // Add required_date column for new Outpass module
            if (!Schema::hasColumn('out_passes', 'required_date')) {
                $table->date('required_date')->nullable()->after('requested_at');
            }
        });

        // Add index for better query performance (wrapped in try-catch for idempotency)
        try {
            Schema::table('out_passes', function (Blueprint $table) {
                $table->index('required_date');
            });
        } catch (\Throwable $e) {
            // Index may already exist
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('out_passes', function (Blueprint $table) {
            $table->dropIndex(['required_date']);
            $table->dropColumn('required_date');
        });
    }
};
