<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Note: This migration adds additional fields to sports_facilities
     * if the table exists, or creates a sports_courts table as fallback.
     */
    public function up(): void
    {
        // Add location and capacity fields to sports_facilities if they don't exist
        if (Schema::hasTable('sports_facilities')) {
            Schema::table('sports_facilities', function (Blueprint $table) {
                if (!Schema::hasColumn('sports_facilities', 'location')) {
                    $table->string('location', 200)->nullable()->after('description');
                }
                if (!Schema::hasColumn('sports_facilities', 'capacity')) {
                    $table->unsignedInteger('capacity')->nullable()->after('location');
                }
                if (!Schema::hasColumn('sports_facilities', 'is_active')) {
                    $table->boolean('is_active')->default(true)->after('capacity');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('sports_facilities')) {
            Schema::table('sports_facilities', function (Blueprint $table) {
                $table->dropColumn(['location', 'capacity', 'is_active']);
            });
        }
    }
};

