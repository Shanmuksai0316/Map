<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds acknowledged_at and acknowledged_by fields to incidents table
     * for Campus Manager emergency acknowledge functionality.
     */
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            if (!Schema::hasColumn('incidents', 'acknowledged_at')) {
                $table->timestamp('acknowledged_at')->nullable()->after('closed_at');
            }
            if (!Schema::hasColumn('incidents', 'acknowledged_by')) {
                $table->unsignedBigInteger('acknowledged_by')->nullable()->after('acknowledged_at');
            }
            if (!Schema::hasColumn('incidents', 'type')) {
                $table->string('type')->nullable()->after('hostel_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropColumn(['acknowledged_at', 'acknowledged_by']);
        });
    }
};

