<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds pickup_code field to laundry_requests table for code-based pickup verification
     */
    public function up(): void
    {
        Schema::table('laundry_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('laundry_requests', 'pickup_code')) {
                $table->string('pickup_code', 4)->nullable()->after('ready_at');
                $table->index('pickup_code');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('laundry_requests', function (Blueprint $table) {
            if (Schema::hasColumn('laundry_requests', 'pickup_code')) {
                $table->dropIndex(['pickup_code']);
                $table->dropColumn('pickup_code');
            }
        });
    }
};
